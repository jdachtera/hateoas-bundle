<?php

namespace uebb\HateoasBundle\Service;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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

    /**
     * @var Reader
     */
    protected $annotationReader;

    public function __construct(ContainerInterface $container, EntityManagerInterface $entityManager, Reader $annotationReader)
    {

        $this->container = $container;
        $this->entityManager = $entityManager;
        $this->annotationReader = $annotationReader;
    }

    protected function getCache()
    {
        return $this->container->get('doctrine_cache.providers.uebb_hateoas_query_cache');
    }

    public function getQueryAbleProperties($entityName)
    {
        $reflectionClass = new \ReflectionClass($this->entityManager->getMetadataFactory()->getMetadataFor($entityName)->getName());

        $properties = array();

        foreach($reflectionClass->getProperties() as $property) {
            /** @var \ReflectionProperty $property */
            $property = $property;
            if ($this->annotationReader->getPropertyAnnotation($property, 'uebb\HateoasBundle\Annotation\QueryAble')) {
                $properties[] = $property->getName();
            }
        }
        return $properties;
    }

    /**
     * @param $entityName
     * @param QueryBuilder $queryBuilder
     * @param $where
     * @return QueryBuilder
     */
    public function applyWhere($entityName, QueryBuilder $queryBuilder, $where, $maxDepth)
    {
        // TODO: Check if everything is escaped properly

        $constraints = $this->parseQuery($where);

        /** @var ClassMetadata $metadata */
        $metadata = $this->getClassMetadata($entityName);
        $entityClass = $metadata->getName();

        $rootAliases = $queryBuilder->getRootAliases();

        $joinedAliases = array();

        foreach ($constraints as $constraint) {
            $propertyParts = explode('.', $constraint['property']);

            $joinedAliases = $this->deepJoinProperties($entityName, $queryBuilder, $propertyParts, $joinedAliases, $maxDepth);

            if (count($propertyParts) === 1) {
                $field = $rootAliases[0] . '.' . $propertyParts[0];
            }  else {
                $field = implode('_', array_merge(array($rootAliases[0]), array_slice($propertyParts, 0, count($propertyParts) - 1))) . '.' . $propertyParts[count($propertyParts) -1];
            }

            if ($constraint['value'] === NULL) {
                $expression = $constraint['comparator'] === '=' ? $queryBuilder->expr()->isNull($field) : $queryBuilder->expr()->isNotNull($field);
            } else {
                $expression = new \Doctrine\ORM\Query\Expr\Comparison(
                    $field,
                    $constraint['comparator'],
                    $this->entityManager->getConnection()->quote($constraint['value'])
                );

            }



            if (strtoupper($constraint['connector']) === 'OR') {
                $queryBuilder->orWhere($expression);
            } else {
                $queryBuilder->andWhere($expression);
            }


        }

        return $joinedAliases;
    }


    public function deepJoinProperties($entityName, QueryBuilder $queryBuilder, $propertyParts, $joinedAliases = array(), $maxDepth = TRUE)
    {
        $metadata = $this->getClassMetadata($entityName);
        $rootAliases = $queryBuilder->getRootAliases();

        for ($i = 0; $i < count($propertyParts); $i++) {

            if ($maxDepth !== TRUE && $maxDepth < $i) {
                throw new AccessDeniedHttpException('You are not allowed to query deeper than ' . $maxDepth . ' properties', NULL, 403);
            }

            if (!in_array($propertyParts[$i], $this->getQueryAbleProperties($metadata->getName()))) {
                throw new AccessDeniedHttpException('You are not allowed to query the property ' . $propertyParts[$i] . ' of the entity type ' . $metadata->getName(), NULL, 403);
            }


            if ($i < count($propertyParts) -1) {
                $metadata = $this->getClassMetadata($metadata->getAssociationTargetClass($propertyParts[$i]));
            } else if ($i === count($propertyParts) -1 && !$metadata->hasField($propertyParts[$i]) && !$metadata->isSingleValuedAssociation($propertyParts[$i])) {
                throw new BadRequestHttpException($propertyParts[$i] . ' is not a scalar field of entity type ' . $metadata->getName(), NULL, 400);
            }

            if ($i > 0) {
                $alias = implode('_', array_merge(array($rootAliases[0]), array_slice($propertyParts, 0, $i)));
                if (!in_array($alias, $joinedAliases)) {
                    $queryBuilder->leftJoin(
                        implode('_', array_merge(array($rootAliases[0]), array_slice($propertyParts, 0, $i - 1))) . '.' . $propertyParts[$i -1],
                        $alias
                    );

                    $joinedAliases[] = $alias;
                }
            }


        }

        return $joinedAliases;
    }

    /**
     * @param $entityName
     * @param QueryBuilder $queryBuilder
     * @param $search
     * @return QueryBuilder
     */
    public function applySearch($entityName, QueryBuilder $queryBuilder, $search)
    {
        /** @var ClassMetadata $metadata */
        $metadata = $this->getClassMetadata($entityName);
        $entityClass = $metadata->getName();

        $rootAliases = $queryBuilder->getRootAliases();

        if ($search !== NULL && trim($search) !== '') {
            $expressions = array();
            foreach ($metadata->getFieldNames() as $field) {
                if (in_array($field, $this->getQueryAbleProperties($entityName))) {

                    $expressions[] = $queryBuilder->expr()->like(
                        $rootAliases[0] . '.' . $field,
                        $this->entityManager->getConnection()->quote('%' . $search . '%')
                    );
                }
            }
            if (count($expressions)) {

                $queryBuilder->andWhere(new Orx($expressions));
            }
        }
    }

    /**
     * @param $entityName
     * @param QueryBuilder $queryBuilder
     * @param $order
     * @return QueryBuilder
     */
    public function applyOrder($entityName, QueryBuilder $queryBuilder, $order, $joinedAliases = array(), $maxDepth)
    {
        /** @var ClassMetadata $metadata */
        $metadata = $this->getClassMetadata($entityName);
        $entityClass = $metadata->getName();

        $rootAliases = $queryBuilder->getRootAliases();

        /**
         * Parse order string: name ASC, age DESC
         */
        if ($order !== NULL && trim($order) !== '') {
            $orders = array();
            $orderStrings = explode(',', $order);

            foreach ($orderStrings as $orderString) {
                $parts = preg_split('/\s+/', trim($orderString));

                $direction = count($parts) > 1 ? strtoupper($parts[1]) : 'ASC';

                $propertyParts = explode('.', $parts[0]);

                $joinedAliases = $this->deepJoinProperties($entityName, $queryBuilder, $propertyParts, $joinedAliases);

                if ($direction !==  'ASC' && $direction !== 'DESC') {
                    $direction = 'ASC';
                }
                $queryBuilder->addOrderBy(new OrderBy(
                    (count($propertyParts) === 1 ? ($rootAliases[0] . '.') : '') . implode('_', array_merge(array($rootAliases[0]), array_slice($propertyParts, 0, count($propertyParts) - 1))) . '.' . $propertyParts[count($propertyParts) -1],
                    $direction
                ));
            }
        }
    }

    /**
     * @param $entityName
     * @param Request $request
     * @param QueryBuilder $queryBuilder
     * @param bool $maxDepth
     * @return QueryBuilder
     */
    public function applyQueryParameters($entityName, Request $request, QueryBuilder $queryBuilder, $maxDepth = TRUE)
    {
        $joinedAliases = $this->applyWhere($entityName, $queryBuilder, $request->query->get('where'), $maxDepth);
        $this->applySearch($entityName, $queryBuilder, $request->query->get('search'));

        $this->applyOrder($entityName, $queryBuilder, $request->query->get('order'), $joinedAliases, $maxDepth);

        return $queryBuilder;
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
        $pattern_connections = '/((?P<connector>AND|OR)\s+)?(?P<comparison>([a-zA-Z.]+)\s*((<|=|>|!=|<=|>=|CONTAINS))\s*(("([^"])*")|(\d+\.\d+)|(\d+)|(NULL)))\s*/';
        $pattern_comparison = '/(?P<property>[a-zA-Z.]+)\s*(?P<comparator>(<|=|>|!=|<=|>=|CONTAINS))\s*(?P<value>("([^"])*")|(\d+\.\d+)|(\d+)|(NULL))/';
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
}