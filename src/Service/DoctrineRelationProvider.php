<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 08.05.14
 * Time: 09:23
 */

namespace uebb\HateoasBundle\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Hateoas\Configuration as Hateoas;
use Hateoas\Configuration\Metadata\ClassMetadataInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class DoctrineRelationProvider
 *
 * Automatically provides the relation links for HATEOAS resources by using the doctrine mapping metadata
 *
 * @package uebb\HateoasBundle\Service
 */
class DoctrineRelationProvider
{

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Logger
     */
    protected $logger;


    /**
     * @param EntityManager $entityManager
     * @param RouterInterface $router
     * @param Logger $logger
     * @param ContainerInterface $container
     */
    public function __construct(EntityManager $entityManager, RouterInterface $router, Logger $logger, ContainerInterface $container)
    {
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->logger = $logger;
        $this->container = $container;
    }

    /**
     * Add the relations for the given classMetadata. Is called by the hateoas bundle
     *
     * @param $object
     * @param ClassMetadataInterface $classMetadata
     * @return array
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function addRelations($object, ClassMetadataInterface $classMetadata)
    {

        /** @var \Doctrine\Common\Cache\CacheProvider $cache */
        $cache = $this->container->get('doctrine_cache.providers.hateoas_relation_cache');

        if ($cache->contains($classMetadata->getName())) {
            return $cache->fetch($classMetadata->getName());
        }

        $relations = array();

        $self_route = 'get_' . strtolower(basename($classMetadata->getName()));

        if ($this->routeExists($self_route)) {
            $relations[] = new Hateoas\Relation(
                'self',
                new Hateoas\Route(
                    $self_route,
                    array('id' => 'expr(object.getId())'),
                    TRUE
                ),
                null,
                array(),
                new Hateoas\Exclusion(
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    'expr(object.getId() === NULL)'
                )
            );
        }
        /** @var ClassMetadata $metadata */
        $metadata = $this->entityManager->getClassMetadata($classMetadata->getName());

        $notExistingRoutes = array();

        foreach ($metadata->getAssociationNames() as $associationName) {
            $mapping = $metadata->getAssociationMapping($associationName);

            $resourceParts = explode("\\", $mapping['targetEntity']);
            $resourceParts[count($resourceParts) - 2] = 'Form';
            $resourceParts[count($resourceParts) - 1] .= 'Type';
            $formType = implode("\\", $resourceParts);
            $type = new $formType();

            if ($metadata->isSingleValuedAssociation($associationName)) {

                $route_name = 'get_' . strtolower($type->getName());

                $args = array('id' => 'expr(object.get' . ucfirst($associationName) . '().getId())');
                $exclusion = new Hateoas\Exclusion(NULL, NULL, NULL, NULL, 'expr(null === object.get' . ucfirst($associationName) . '())');
                if (!$this->routeExists($route_name)) {
                    $route_name = $self_route . '_' . strtolower($associationName);
                }
            } else {
                $route_name = $self_route . '_' . strtolower($associationName);
                $args = array('id' => 'expr(object.getId())');
                $exclusion = NULL;
            }
            if ($this->routeExists($route_name)) {
                if ($mapping['fetch'] === ClassMetadataInfo::FETCH_EAGER) {
                    $embedded = 'expr(object.get' . ucfirst($associationName) . '())';
                } else {
                    $embedded = NULL;
                }
                $relations[] = new Hateoas\Relation($associationName, new Hateoas\Route($route_name, $args, TRUE), $embedded, array(), $exclusion);
            } else {
                //if ($mapping['fetch'] === ClassMetadataInfo::FETCH_EAGER) {
                //    $embedded = 'expr(object.get' . ucfirst($associationName) . '())';
                //    $relations[] = new Hateoas\Relation($associationName, NULL, $embedded, array(), $exclusion);
                //}
                // Why doesn't it exist?
                // print_r($route_name);
                $notExistingRoutes[] = $route_name;
            }
        }
        if (count($notExistingRoutes)) {
            $this->logger->addWarning('There are possibly missing routes', $notExistingRoutes);
        }

        $cache->save($classMetadata->getName(), $relations);

        // You need to return the relations
        // Adding the relations to the $classMetadata won't work
        return $relations;
    }

    /**
     * Check if a route exists
     *
     * @param $name
     * @return bool
     */
    protected function routeExists($name)
    {
        return $this->router->getRouteCollection()->get($name) instanceof Route;
    }

}