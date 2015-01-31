<?php

namespace uebb\HateoasBundle\Service;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * Class LinkListener
 *
 * This class parses HATEOAS style links in the request body and request header resolves them and adds them to the request attributes
 *
 * @package uebb\HateoasBundle\Service
 */
class LinkParser
{

    /**
     * @var ControllerResolverInterface
     */
    private $resourceLinkResolver;

    /**
     * @param LinkResolver $resourceLinkResolver
     */
    public function __construct(LinkResolver $resourceLinkResolver)
    {
        $this->resourceLinkResolver = $resourceLinkResolver;
    }

    /**
     * @param GetResponseEvent $event
     * @return array|null
     */
    public function parseLinks(GetResponseEvent $event)
    {
        if ($event->getRequest()->headers->has('link')) {

            $links = array();
            $header = $event->getRequest()->headers->get('link');

            /*
             * Due to limitations, multiple same-name headers are sent as comma
             * separated values.
             *
             * This breaks those headers into Link headers following the format
             * http://tools.ietf.org/html/rfc2068#section-19.6.2.4
             */
            while (preg_match('/^((?:[^"]|"[^"]*")*?),/', $header, $matches)) {
                $header = trim(substr($header, strlen($matches[0])));
                $links[] = $matches[1];
            }

            if ($header) {
                $links[] = $header;
            }

            $linkTable = array();

            foreach ($links as $link) {
                $linkParams = explode(';', trim($link));
                $resource = trim(preg_replace('/<|>/', '', array_shift($linkParams)));
                $rel = trim(preg_replace('/rel="|"/', '', array_shift($linkParams)));

                $linkTable[$rel][] = $resource;
            }

            $event->getRequest()->attributes->set('_links', $linkTable);
        } else {
            if ($event->getRequest()->request->has('_links')) {

                $links = $event->getRequest()->request->get('_links');
                $event->getRequest()->request->remove('_links');

                forEach ($links as $rel => $resources) {
                    if (!is_array($resources)) {
                        throw new \InvalidArgumentException('Format must be _links: {..rel..: {href: ... }}');
                    }
                    if ((bool)count(array_filter(array_keys($resources), 'is_string'))) {
                        $resources = array($resources);
                    }
                    $rel_links = array();
                    foreach ($resources as $resource) {

                        $rel_links[] = $resource['href'];
                    }
                    $links[$rel] = $rel_links;
                }

                $event->getRequest()->attributes->set('_links', $links);
            }
        }
    }

}