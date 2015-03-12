<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 12.03.15
 * Time: 06:29
 */

namespace uebb\HateoasBundle\Service;


use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;

class RouteResolver {

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(RouterInterface $router, Cache $cache, EntityManagerInterface $entityManager)
    {
        $this->router = $router;
        $this->cache = $cache;
        $this->entityManager = $entityManager;
    }

    /**
     * @param String $resourceClass
     * @param String $action
     * @return mixed
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \Exception
     */
    public function resolveRouteName($resourceClass, $action, $args = array())
    {
        $key = serialize(array($resourceClass , $action, $args));


        if ($this->cache->contains('routes', $key)) {
            return $this->cache->fetch('routes', $key);
        }

        foreach ($this->router->getRouteCollection()->all() as $name => /** @var Route $route */ $route) {
            $defaults = $route->getDefaults();

            $parts = explode('::', $defaults['_controller']);

            if (count($parts) === 2) {
                $reflection = new \ReflectionClass($parts[0]);
                $defaultProperties = $reflection->getdefaultProperties();
                if (
                    is_subclass_of($parts[0], '\\uebb\\HateoasBundle\\Controller\\HateoasController') &&
                    $parts[1] === $action &&
                    $this->entityManager->getMetadataFactory()->getMetadataFor(
                        $defaultProperties['entityName']
                    )->getName() === $resourceClass
                ) {
                    if ($args === array_intersect_key($defaults, $args)) {
                        $this->cache->save('routes', $key, $name);
                        return $name;
                    }
                }
            }
        }

        $this->cache->save('routes', $key, null);

        return null;
    }
}