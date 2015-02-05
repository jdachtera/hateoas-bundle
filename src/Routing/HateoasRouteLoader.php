<?php
namespace uebb\HateoasBundle\Routing;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Routing\Route;

/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 04.02.15
 * Time: 14:57
 */

class HateoasRouteLoader extends \FOS\RestBundle\Routing\Loader\RestRouteLoader {


    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource)
        && 'hateoas' === $type
        && !in_array(pathinfo($resource, PATHINFO_EXTENSION), array('xml', 'yml')
        );
    }



    /**
     * {@inheritdoc}
     */
    public function load($controller, $type = null)
    {
        list($prefix, $class) = $this->getControllerLocator($controller);

        $collection = $this->controllerReader->read(new \ReflectionClass($class));
        $collection->prependRouteControllersWithPrefix($prefix);
        $collection->setDefaultFormat($this->defaultFormat);

        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $inflector = $this->container->get('fos_rest.inflector');

        $reflection = new \ReflectionClass($controller);

        if ($reflection->isSubclassOf('uebb\\HateoasBundle\\Controller\\HateoasController')) {
            $defaultProperties = $reflection->getdefaultProperties();
            /** @var ClassMetadata $metadata */
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor($defaultProperties['entityName']);

            foreach ($metadata->getAssociationNames() as $associationName) {
                if ($metadata->isCollectionValuedAssociation($associationName)) {
                    foreach(array('get', 'patch', 'post') as $method) {
                        if (!$reflection->hasMethod('get' . ucfirst($associationName) . 'Action')) {
                            $resource  = preg_split(
                                '/([A-Z][^A-Z]*)Controller/', $reflection->getShortName(), -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
                            );
                            
                            $routeName =  $method . '_' . strtolower(implode('_', $resource)) . '_'. strtolower($associationName);

                            $urlParts = array();

                            $urlParts[] = $inflector->pluralize(strtolower(implode('_', $resource)));

                            $urlParts[] = '{id}';
                            $urlParts[] = strtolower($associationName);

                            $pattern      = implode('/', $urlParts);
                            $defaults     = array('_controller' => $prefix . $method . 'Linkcollection', 'rel' => $associationName);
                            $requirements = array('_method' => strtoupper($method));
                            $options      = array();
                            $host         = '';
                            $schemes      = array();
                            $condition    = null;


                            $route = new Route(
                                $pattern, $defaults, $requirements, $options, $host, $schemes, null, $condition
                            );
                            $collection->add($routeName, $route);


                        }
                    }
                }
            }
        }

        return $collection;

    }

    /**
     * Returns controller locator by it's id.
     *
     * @param string $controller
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    private function getControllerLocator($controller)
    {
        $class  = null;
        $prefix = null;

        if (0 === strpos($controller, '@')) {
            $file = $this->locator->locate($controller);
            $controllerClass = $this->findClass($file);

            if (false === $controllerClass) {
                throw new \InvalidArgumentException(sprintf('Can\'t find class for controller "%s"', $controller));
            }

            $controller = $controllerClass;
        }

        if ($this->container->has($controller)) {
            // service_id
            $prefix = $controller.':';
            $this->container->enterScope('request');
            $this->container->set('request', new Request());
            $class = get_class($this->container->get($controller));
            $this->container->leaveScope('request');
        } elseif (class_exists($controller)) {
            // full class name
            $class  = $controller;
            $prefix = $class.'::';
        } elseif (false !== strpos($controller, ':')) {
            // bundle:controller notation
            try {
                $notation             = $this->controllerParser->parse($controller.':method');
                list($class, ) = explode('::', $notation);
                $prefix               = $class.'::';
            } catch (\Exception $e) {
                throw new \InvalidArgumentException(
                    sprintf('Can\'t locate "%s" controller.', $controller)
                );
            }
        }

        if (empty($class)) {
            throw new \InvalidArgumentException(sprintf(
                'Class could not be determined for Controller identified by "%s".', $controller
            ));
        }

        return array($prefix, $class);
    }
}