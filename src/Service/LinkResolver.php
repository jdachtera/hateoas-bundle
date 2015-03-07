<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 08.05.14
 * Time: 12:00
 */

namespace uebb\HateoasBundle\Service;

use FOS\RestBundle\View\View;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use uebb\HateoasBundle\Entity\ResourceInterface;
use uebb\HateoasBundle\Exception\InvalidLinkException;

/**
 * Class ResourceLinkResolver
 *
 * Resolves HATEOAS Links to resources
 *
 * @package uebb\HateoasBundle\Service
 */
class LinkResolver implements LinkResolverInterface
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
        EventDispatcherInterface $dispatcher
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
        $linkTable = array();

        foreach ($links as $rel => $resources) {
            foreach ($resources as $resource) {
                $linkTable[$rel][] = $this->resolveResourceLink($resource['href']);
            }
        }

        return $linkTable;
    }

    /**
     *
     *
     * @param $href - The resource link
     * @return ResourceInterface
     */
    public function resolveResourceLink($href)
    {
        $stubRequest = Request::create($href);
        // External url
        if (
            $this->urlMatcher->getContext()->getHost() !== $stubRequest->getHost() ||
            ($stubRequest->isSecure() ?
                $this->urlMatcher->getContext()->getHttpsPort() !== $stubRequest->getPort() :
                $this->urlMatcher->getContext()->getHttpPort() !== $stubRequest->getPort()
            ) ||
            $this->urlMatcher->getContext()->getBaseUrl() !== $stubRequest->getBaseUrl()
        ) {
            throw new InvalidLinkException($href);
        }

        $path = $stubRequest->getPathInfo();

        $requestMethod = $this->urlMatcher->getContext()->getMethod();
        // Force the GET method to avoid the use of the
        // previous method (LINK/UNLINK)
        $this->urlMatcher->getContext()->setMethod('GET');

        try {
            $route = $this->urlMatcher->match($path);
        } catch(ResourceNotFoundException $e) {
            throw new InvalidLinkException($href);
        }

        // Set back to original method
        $this->urlMatcher->getContext()->setMethod($requestMethod);

        foreach ($route as $key => $attr) {
            $stubRequest->attributes->set($key, $attr);
        }

        if (false === $controller = $this->resolver->getController($stubRequest)) {
            throw new InvalidLinkException($href);
        }

        // Make sure @ParamConverter and friends are handled
        $subEvent = new FilterControllerEvent($this->kernel, $controller, $stubRequest, HttpKernelInterface::MASTER_REQUEST);
        $this->dispatcher->dispatch(KernelEvents::CONTROLLER, $subEvent);
        $controller = $subEvent->getController();

        $arguments = $this->resolver->getArguments($stubRequest, $controller);

        $result = call_user_func_array($controller, $arguments);

        if ($result instanceof View) {
            $result = $result->getData();
        }
        return $result;
    }

}