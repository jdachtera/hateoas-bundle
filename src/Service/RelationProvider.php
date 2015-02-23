<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 08.05.14
 * Time: 09:23
 */

namespace uebb\HateoasBundle\Service;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Hateoas\Configuration as Hateoas;
use Hateoas\Configuration\Metadata\ClassMetadataInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use uebb\HateoasBundle\Entity\ResourceInterface;
use uebb\HateoasBundle\Entity\Root;
use uebb\HateoasBundle\Entity\User;

/**
 * Class RelationProvider
 *
 * Automatically provides the relation links for HATEOAS resources by using the doctrine mapping metadata
 *
 * @package uebb\HateoasBundle\Service
 */
class RelationProvider
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
     * @var TokenStorageInterface
     */
    protected $tokenStorage;


    /**
     * @param EntityManager $entityManager
     * @param RouterInterface $router
     * @param Logger $logger
     * @param ContainerInterface $container
     */
    public function __construct(EntityManager $entityManager, RouterInterface $router, Logger $logger, ContainerInterface $container, TokenStorageInterface $tokenStorage)
    {
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->logger = $logger;
        $this->container = $container;
        $this->tokenStorage = $tokenStorage;
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
        $cache = $this->container->get('doctrine_cache.providers.uebb_hateoas_relation_cache');

        if ($cache->contains($classMetadata->getName())) {
            return $cache->fetch($classMetadata->getName());
        }

        $relations = array();

        $classImplements = class_implements($classMetadata->getName());
        
        if (in_array('uebb\\HateoasBundle\\Entity\\RootInterface', $classImplements)) {
            $relations = $this->addRootRelations($object, $classMetadata);
        } else if (in_array('uebb\\HateoasBundle\\Entity\\ResourceInterface', $classImplements)) {
            $selfRoute = $this->findRoute($classMetadata->getName(), 'getAction');

            if ($selfRoute) {
                $relations[] = new Hateoas\Relation(
                    'self',
                    new Hateoas\Route(
                        $selfRoute,
                        array('id' => 'expr(object.getId())'),
                        FALSE
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
            if (is_subclass_of($classMetadata->getName(), 'uebb\\HateoasBundle\\Entity\\File')) {
                $downloadRoute = $this->findRoute($classMetadata->getName(), 'getDownloadAction');

                $relations[] = new Hateoas\Relation(
                    'download',
                    new Hateoas\Route(
                        $downloadRoute,
                        array('id' => 'expr(object.getId())'),
                        FALSE
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

                $routeName = NULL;

                if ($metadata->isSingleValuedAssociation($associationName)) {
                    $routeName = $this->findRoute($mapping['targetEntity'], 'getAction');
                    $args = array('id' => 'expr(object.get' . ucfirst($associationName) . '().getId())');
                    $exclusion = new Hateoas\Exclusion(NULL, NULL, NULL, NULL, 'expr(null === object.get' . ucfirst($associationName) . '())');
                } else {

                    $routeName = $this->findRoute($classMetadata->getName(), 'get' . ucfirst($associationName) . 'Action');

                    $args = array('id' => 'expr(object.getId())');
                    if (!$routeName) {
                        $routeName = $this->findRoute($classMetadata->getName(), 'getLinkcollection', array('rel' => $associationName));
                        $args['rel'] = $associationName;
                    }
                    $exclusion = NULL;
                }

                if ($routeName) {
                    if ($mapping['fetch'] === ClassMetadataInfo::FETCH_EAGER) {
                        $embedded = 'expr(object.get' . ucfirst($associationName) . '())';
                    } else {
                        $embedded = NULL;
                    }
                    $relations[] = new Hateoas\Relation($associationName, new Hateoas\Route($routeName, $args, FALSE), $embedded, array(), $exclusion);
                }
            }
            if (count($notExistingRoutes)) {
                $this->logger->addWarning('There are possibly missing routes', $notExistingRoutes);
            }
        }

        $cache->save($classMetadata->getName(), $relations);

        return $relations;

    }

    protected function addRootRelations(Root $object, ClassMetadataInterface $classMetadata)
    {
        $prefix = $object->getPrefix();
        $prefixLength = strlen($prefix);

        $relations = array();
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            /** @var ClassMetadata $metadata */
            $metadata = $metadata;

            if (
                !$metadata->isMappedSuperclass && !$metadata->isEmbeddedClass &&
                in_array('uebb\\HateoasBundle\\Entity\\ResourceInterface', class_implements($metadata->getName()))
            ) {
                $routeName = $this->findRoute($metadata->getName(), 'cgetAction');
                if ($routeName) {
                    $route = $this->router->getRouteCollection()->get($routeName);
                    if (substr($route->getPath(), 0, $prefixLength) === $prefix) {
                        $relations[] = new Hateoas\Relation(substr($routeName, 4), new Hateoas\Route($routeName, array(), FALSE));
                    }
                }
            }
        }

        $user = $this->tokenStorage->getToken()->getUser();


        if ($user instanceof User) {

            $routeName = $this->findRoute(get_class($user), 'getAction');
            if($routeName) {
                $relations[] = new Hateoas\Relation('currentUser', new Hateoas\Route($routeName, array(
                    'id' => $user->getId()
                ), FALSE));
            }
        }

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

    /**
     * @param String $resourceClass
     * @param String $action
     * @return mixed
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \Exception
     */
    protected function findRoute($resourceClass, $action, $args = array())
    {

        foreach($this->router->getRouteCollection()->all() as $name => /** @var Route $route */$route) {
            $defaults = $route->getDefaults();

            $parts = explode('::',$defaults['_controller']);

            if (count($parts) === 2) {
                $reflection = new \ReflectionClass($parts[0]);
                $defaultProperties = $reflection->getdefaultProperties();
                if (
                    is_subclass_of($parts[0], '\\uebb\\HateoasBundle\\Controller\\HateoasController') &&
                    $parts[1] === $action &&
                    $this->entityManager->getMetadataFactory()->getMetadataFor($defaultProperties['entityName'])->getName() === $resourceClass
                ) {
                    if ($args === array_intersect_key($defaults, $args)) {
                        return $name;
                    }
                }
            }
        }
        return NULL;
    }
}