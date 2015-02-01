<?php

namespace uebb\HateoasBundle\Service;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class QueryParser
{

    /**
     * @var ContainerInterface
     */
    protected $container;


    /**
     * @var EntityManager
     */
    protected $entityManager;

    public function __construct(ContainerInterface $container, EntityManagerInterface $entityManager)
    {
        $this->container = $container;
        $this->entityManager = $entityManager;
    }

    protected function getCache()
    {
        return $this->container->get('doctrine_cache.providers.uebb_hateoas_query_cache');
    }

    /**
     * Construct a Doctrine Criteria object from a Symfony web request
     *
     * @param string $entityName - The entity name to construct the query for
     * @param Request $request - The request
     * @return Criteria
     */
    public function getCriteria($entityName, Request $request)
    {

        $cache_identifier = json_encode(array(
            'entityName' => $entityName,
            'request' => $request->query->all()
        ));

        if ($this->getCache()->contains($cache_identifier)) {
            return $this->getCache()->fetch($cache_identifier);
        }

        $criteria = new Criteria();
        $constraints = $this->parseQuery($request->query->get('where'));

        /** @var ClassMetadata $metadata */
        $metadata = $this->getClassMetadata($entityName);
        $entityClass = $metadata->getName();

        foreach ($constraints as $constraint) {
            if (in_array($constraint['property'], $entityClass::getQueryableProperties())) {
                $expression = new Comparison($constraint['property'], $constraint['comparator'], $constraint['value']);
                if (strtoupper($constraint['connector']) === 'OR') {
                    $criteria->orWhere($expression);
                } else {
                    $criteria->andWhere($expression);
                }
            } else {
                throw new AccessDeniedHttpException('You are not allowed to query the property ' . $constraint['property'] . ' of the entity type ' . $entityName, NULL, 403);
            }
        }

        $search = $request->query->get('search');
        if ($search !== NULL && trim($search) !== '') {
            $expressions = array();
            foreach ($metadata->getFieldNames() as $field) {
                if (in_array($field, $entityClass::getQueryableProperties())) {
                    $expressions[] = $criteria->expr()->contains($field, $search);
                }
            }
            if (count($expressions)) {
                $criteria->andWhere(new CompositeExpression(CompositeExpression::TYPE_OR, $expressions));
            }
        }

        $order = $request->query->get('order');
        /**
         * Parse order string: name ASC, age DESC
         */
        if ($order !== NULL && trim($order) !== '') {
            $orders = array();
            $orderStrings = explode(',', $order);
            foreach ($orderStrings as $orderString) {
                $parts = preg_split('/\s+/', trim($orderString));
                $direction = count($parts) > 1 ? $parts[1] : Criteria::ASC;
                if ($direction !== Criteria::ASC && $direction !== Criteria::DESC) {
                    $direction = Criteria::ASC;
                }
                $orders[$parts[0]] = $direction;
            }
            $criteria->orderBy($orders);
        }

        $this->getCache()->save($cache_identifier, $criteria);

        return $criteria;
    }

    /**
     * Parses a query where string into an array
     *
     * @param string $whereString - The where string
     * @return array
     */
    protected function parseQuery($whereString)
    {
        if (!is_string($whereString)) {
            return array();
        }
        $pattern_connections = '/((?P<connector>AND|OR)\s+)?(?P<comparison>([a-zA-Z]+)\s*((<|=|>|!=|<=|>=|CONTAINS))\s*(("([^"])*")|(\d+\.\d+)|(\d+)|(NULL)))\s*/';
        $pattern_comparison = '/(?P<property>[a-zA-Z]+)\s*(?P<comparator>(<|=|>|!=|<=|>=|CONTAINS))\s*(?P<value>("([^"])*")|(\d+\.\d+)|(\d+)|(NULL))/';
        $pattern_float = '/\./';

        $constraints = array();
        preg_match_all($pattern_connections, $whereString, $matches);
        foreach ($matches['connector'] as $index => $connector) {
            $connector = $connector == '' ? 'AND' : $connector;
            preg_match($pattern_comparison, $matches['comparison'][$index], $comparison);
            $value = $comparison['value'];
            if ($value[0] === '"') {
                $value = substr($value, 1, strlen($value) - 2);
            } else if ($value === 'NULL') {
                $value = NULL;
            } else if (preg_match($pattern_float, $value)) {
                $value = floatval($value);
            } else {
                $value = intval($value);
            }

            if ($comparison['comparator'] === '!=') {
                $comparison['comparator'] = '<>';
            }
            $constraints[] = array(
                'connector' => $connector,
                'property' => $comparison['property'],
                'comparator' => $comparison['comparator'],
                'value' => $value
            );
        }
        return $constraints;
    }

    /**
     * @param $entityName
     * @return \Doctrine\Common\Persistence\Mapping\ClassMetadata
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \Exception
     */
    protected function getClassMetadata($entityName)
    {
        return $this->entityManager->getMetadataFactory()->getMetadataFor($entityName);
    }

    public function parseFilter($entityName, Request $request, QueryBuilder $queryBuilder)
    {
        /** @var ClassMetadata $metadata */
        $metadata = $this->getClassMetadata($entityName);

        $entityClass = $metadata->getName();

        $filter_by = $request->get('filter');

        // Easily query by related ids via filter[...property] = !1,2...
        if (is_array($filter_by)) {
            foreach ($metadata->getAssociationMappings() as $name => $mapping) {
                $values = (isset($filter_by[$name]) && trim($filter_by[$name]) !== '') ? explode(',', $filter_by[$name]) : false;
                if ($values) {
                    if (!in_array($name, $entityClass::getQueryableProperties())) {
                        throw new AccessDeniedHttpException('You are not allowed to query the relation ' . $name . ' of the entity type ' . $entityName, NULL, 403);
                    }
                    $in = array();
                    $not_in = array();

                    foreach ($values as $value) {
                        if (strlen($value)) {
                            if ($value[0] === '!') {
                                $not_in[] = intval(substr($value, 1), 10);
                            } else {
                                $in[] = intval($value, 10);
                            }
                        }
                    }

                    if (count($in)) {
                        $queryBuilder->innerJoin('e.' . $name, $name);
                        $queryBuilder->andWhere($name . '.id IN (:filter_by_in_' . $name . ')');
                        $queryBuilder->setParameter('filter_by_in_' . $name, $in);
                    }
                    // not in needs an exists subquery
                    if (count($not_in)) {
                        $mapping = $metadata->getAssociationMapping($name);

                        /** @var \Doctrine\ORM\Query|\Doctrine\ORM\QueryBuilder $subquery */
                        $subquery = $this->getRepository($mapping['targetEntity'])->createQueryBuilder('e_' . $mapping['fieldName']);
                        $reverseProperty = ($mapping['isOwningSide'] ? $mapping['inversedBy'] : $mapping['mappedBy']);
                        $subquery->leftJoin('e_' . $mapping['fieldName'] . '.' . $reverseProperty, 'e_' . $mapping['fieldName'] . '_' . $reverseProperty);

                        $subquery->where(
                            $subquery->expr()->andX(
                                $subquery->expr()->eq('e_' . $mapping['fieldName'] . '_' . $reverseProperty . '.id', 'e.id'),
                                $subquery->expr()->in('e_' . $mapping['fieldName'] . '.id', $not_in)
                            )
                        );

                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->andX(
                                $queryBuilder->expr()->not(
                                    $queryBuilder->expr()->exists($subquery->getDQL())
                                )
                            )

                        );

                    }
                }

            }
        }
    }
}