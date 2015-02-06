<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 08.05.14
 * Time: 12:00
 */

namespace uebb\HateoasBundle\Service;

use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use uebb\HateoasBundle\Entity\ResourceInterface;

/**
 * Class ResourceLinkResolver
 *
 * Resolves HATEOAS Links to resources
 *
 * @package uebb\HateoasBundle\Service
 */
class LinkResolver
{

    /**
     * @var ControllerResolverInterface
     */
    private $resolver;
    private $urlMatcher;
    private $kernel;
    private $dispatcher;

    /**
     * @param ControllerResolverInterface $controllerResolver The 'controller_resolver' service
     * @param UrlMatcherInterface $urlMatcher The 'router' service
     */
    public function __construct(
        ControllerResolverInterface $controllerResolver,
        UrlMatcherInterface $urlMatcher,
        HttpKernelInterface $kernel,
        $dispatcher
    )
    {
        $this->resolver = $controllerResolver;
        $this->urlMatcher = $urlMatcher;
        $this->kernel = $kernel;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Resolve an array of links in the format
     *  array(
     *      rel => array(...links...)
     * )
     *
     * @param array $links
     * @return array
     */
    public function resolveResourceLinks($links)
    {
        if (!count($links)) {
            return array();
        }

        // The controller resolver needs a request to resolve the controller.
        $stubRequest = new Request();

        $requestMethod = $this->urlMatcher->getContext()->getMethod();
        // Force the GET method to avoid the use of the
        // previous method (LINK/UNLINK)
        $this->urlMatcher->getContext()->setMethod('GET');

        $linkTable = array();

        foreach ($links as $rel => $resources) {
            foreach ($resources as $resource) {
                $value = $this->resolveResourceLink($resource, $stubRequest);
                if ($value === null) {
                    throw new BadRequestHttpException('Resource \'' . $resource . '\' could not be resolved');
                }
                $linkTable[$rel][] = $value;
            }
        }
        $this->urlMatcher->getContext()->setMethod($requestMethod);
        return $linkTable;
    }

    /**
     * Resolve a single HATEOAS link
     *
     * @param $resource - The resource link
     * @param Request $sourceRequest - Optional source request
     * @return ResourceInterface|null
     */
    public function resolveResourceLink($resource, Request $sourceRequest = null)
    {
        $stubRequest = Request::create($resource);
        if ($sourceRequest !== NULL) {
            $stubRequest->attributes->replace($sourceRequest->attributes->all());
        }

        $path = $stubRequest->getPathInfo();
        $baseUrl = $this->urlMatcher->getContext()->getBaseUrl();

        // External url
        if (substr($path, 0, strlen($baseUrl)) !== $baseUrl) {
            return $resource;
        }

        $path = substr($path, strlen($baseUrl));

        try {
            $route = $this->urlMatcher->match($path);
        } catch (\Exception $e) {
            // If we don't have a matching route we return
            // the original Link header
            return $resource;
        }
        foreach ($route as $key => $attr) {
            $stubRequest->attributes->set($key, $attr);
        }


        if (false === $controller = $this->resolver->getController($stubRequest)) {
            return null;
        }


        // Make sure @ParamConverter and friends are handled
        $subEvent = new FilterControllerEvent($this->kernel, $controller, $stubRequest, HttpKernelInterface::MASTER_REQUEST);
        $this->dispatcher->dispatch(KernelEvents::CONTROLLER, $subEvent);
        $controller = $subEvent->getController();

        $arguments = $this->resolver->getArguments($stubRequest, $controller);

        $result = call_user_func_array($controller, $arguments);
        if ($result instanceof View) {
            return $result->getData();
        }
        return $result;
    }


}