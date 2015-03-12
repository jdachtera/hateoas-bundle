<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 08.05.14
 * Time: 09:23
 */

namespace uebb\HateoasBundle\Service;

use Doctrine\Common\Util\Inflector;
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
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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
     * @var Cache
     */
    protected $cache;

    /**
     * @var RouteResolver
     */
    protected $routeResolver;

    /**
     * @var RouterInterface
     */
    protected $router;

    protected static $IMAGE_ARGUMENTS = array('width', 'height', 'format');
    protected static $COLLECTION_ARGUMENTS = array('where', 'order', 'limit', 'page');

    /**
     * @param EntityManager $entityManager
     * @param RouterInterface $router
     */
    public function __construct(EntityManager $entityManager, RouteResolver $routeResolver, RouterInterface $router, Cache $cache) {
        $this->entityManager = $entityManager;
        $this->routeResolver = $routeResolver;
        $this->router = $router;
        $this->cache = $cache;
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
        if ($this->cache->contains('relations', $classMetadata->getName())) {
            return $this->cache->fetch('relations', $classMetadata->getName());
        }

        $relations = array();

        $classImplements = class_implements($classMetadata->getName());

        if (in_array('uebb\\HateoasBundle\\Entity\\ResourceInterface', $classImplements)) {
            $selfRoute = $this->routeResolver->resolveRouteName($classMetadata->getName(), 'getAction');

            if ($selfRoute) {
                $relations[] = new Hateoas\Relation(
                    'self',
                    new Hateoas\Route($selfRoute, array('id' => 'expr(object.getId())'),false),
                    null,
                    array(),
                    new Hateoas\Exclusion(null, null, null, null, 'expr(object.getId() === NULL)')
                );
            }
            if (is_subclass_of($classMetadata->getName(), 'uebb\\HateoasBundle\\Entity\\File')) {
                $downloadRoute = $this->routeResolver->resolveRouteName($classMetadata->getName(), 'getDownloadAction');

                $arguments = array('id' => 'expr(object.getId())');

                if (in_array('uebb\\HateoasBundle\\Entity\\ImageInterface', $classImplements)) {
                    foreach (self::$IMAGE_ARGUMENTS as $argument) {
                        $arguments[$argument] = '{'.$argument.'}';
                    }
                }

                $relations[] = new Hateoas\Relation(
                    'download',
                    new Hateoas\Route($downloadRoute, $arguments, false),
                    null,
                    array(),
                    new Hateoas\Exclusion(null, null, null, null, 'expr(object.getId() === NULL)'));
            }

            /** @var ClassMetadata $metadata */
            $metadata = $this->entityManager->getClassMetadata($classMetadata->getName());

            $notExistingRoutes = array();

            foreach ($metadata->getAssociationNames() as $associationName) {
                $mapping = $metadata->getAssociationMapping($associationName);

                $routeName = null;

                if ($metadata->isSingleValuedAssociation($associationName)) {
                    $routeName = $this->routeResolver->resolveRouteName($mapping['targetEntity'], 'getAction');
                    $args = array('id' => 'expr(object.get'.ucfirst($associationName).'().getId())');
                    $exclusion = new Hateoas\Exclusion(null, null, null, null, 'expr(null === object.get'.ucfirst($associationName).'())');
                } else {

                    $routeName = $this->routeResolver->resolveRouteName(
                        $classMetadata->getName(),
                        'get'.ucfirst($associationName).'Action'
                    );

                    $args = array('id' => 'expr(object.getId())');
                    if (!$routeName) {
                        $routeName = $this->routeResolver->resolveRouteName(
                            $classMetadata->getName(),
                            'getLinkcollection',
                            array('rel' => $associationName)
                        );
                        $args['rel'] = $associationName;
                    }
                    $exclusion = null;
                }

                if ($routeName) {
                    if ($mapping['fetch'] === ClassMetadataInfo::FETCH_EAGER) {
                        $embedded = 'expr(object.get'.ucfirst($associationName).'())';
                    } else {
                        $embedded = null;
                    }
                    $relations[] = new Hateoas\Relation(
                        $associationName,
                        new Hateoas\Route($routeName, $args, false),
                        $embedded,
                        array(),
                        $exclusion
                    );
                }
            }
        }

        $this->cache->save('relations', $classMetadata->getName(), $relations);

        return $relations;

    }


    public function addRootRelations(Root $root, ClassMetadataInterface $classMetadata)
    {
        $prefix = $root->getPrefix();
        $relations = array();

        $self_args = $this->router->match($root->getPrefix());

        $relations[] = new Hateoas\Relation('self', new Hateoas\Route($self_args['_route'], array(), false));

        foreach($root->getEntityNames() as $rel => $entityName) {
            /** @var ClassMetadata $metadata */
            $metadata = $this->entityManager->getMetadataFactory()->getMetadataFor($entityName);

            $routeName = $this->routeResolver->resolveRouteName($metadata->getName(), 'cgetAction');
            if ($routeName) {
                $arguments = array();
                foreach (self::$COLLECTION_ARGUMENTS as $argument) {
                    $arguments[$argument] = '{'.$argument.'}';
                }
                $relations[] = new Hateoas\Relation(Inflector::pluralize($rel), new Hateoas\Route($routeName, $arguments, false));
            }
            $routeName = $this->routeResolver->resolveRouteName($metadata->getName(), 'getAction');
            if ($routeName) {
                $relations[] = new Hateoas\Relation($rel, new Hateoas\Route($routeName, array('id' => "{id}"), false));
            }
            $routeName = $self_args['_route'] . '_schema';
            if ($this->router->getRouteCollection()->get($routeName)) {
                $relations[] = new Hateoas\Relation('schema:' . $rel, new Hateoas\Route($routeName, array('rel' => $rel), false));
            }
        }

        $user = $root->getCurrentUser();

        if ($user instanceof User) {
            $routeName = $this->routeResolver->resolveRouteName(get_class($user), 'getAction');

            if ($routeName) {

                $relations[] = new Hateoas\Relation('currentUser', new Hateoas\Route($routeName, array('id' => $user->getId()), false));
            }
        }

        return $relations;
    }

}