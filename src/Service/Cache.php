<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 11.03.15
 * Time: 16:59
 */

namespace uebb\HateoasBundle\Service;


use Doctrine\Common\Cache\ArrayCache;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Cache {

    /**
     * @var \Doctrine\Common\Cache\CacheProvider
     */
    protected $cacheProvider;
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        /** @var \Doctrine\Common\Cache\CacheProvider $cache */
        if ($this->container->has('doctrine_cache.providers.uebb_hateoas')) {
            $this->cacheProvider = $this->container->get('doctrine_cache.providers.uebb_hateoas');
        } else {
            $this->cacheProvider = new ArrayCache();
        }
    }

    /**
     * @param string $namespace
     * @param string $key
     * @return bool
     */
    public function contains($namespace, $key)
    {
        return $this->cacheProvider->contains($namespace . '.' . $key);
    }

    /**
     * @param string $namespace
     * @param string $key
     * @return bool|mixed|string
     */
    public function fetch($namespace, $key)
    {
        return $this->cacheProvider->fetch($namespace . '.' . $key);
    }

    /**
     * @param string $namespace
     * @param string $key
     * @param mixed $data
     * @param int $lifetime
     * @return bool
     */
    public function save($namespace, $key, $data, $lifetime = 0)
    {
        return $this->cacheProvider->save($namespace . '.' . $key, $data, $lifetime);
    }

}