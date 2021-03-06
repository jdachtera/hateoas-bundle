<?php

namespace uebb\HateoasBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Class LinkListener
 *
 * This class parses HATEOAS style links in the request body and request header resolves them and adds them to the request attributes
 *
 * @package uebb\HateoasBundle\Service
 */
class LinkParser implements LinkParserInterface
{

    const LINKS_PROPERTY = '_links';
    const LINKS_HEADER = 'link';

    public function parseHeaderLinks($header)
    {
        $links = array();

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

            $linkTable[$rel][] = array('href' => $resource);
        }

        return $linkTable;
    }

    public function parseBodyLinks($links)
    {
        $linkTable = array();

        if (is_array($links)) {
            forEach ($links as $rel => $resources) {
                if (is_array($resources)) {
                    if ((bool)count(array_filter(array_keys($resources), 'is_string'))) {
                        $resources = array($resources);
                    }
                    $rel_links = array();
                    foreach ($resources as $resource) {

                        $rel_links[] = array('href' => $resource['href']);
                    }
                    $linkTable[$rel] = $rel_links;
                }
            }
        }
        return $linkTable;
    }

    /**
     * @param GetResponseEvent $event
     * @return array
     */
    public function parseRequestLinks(Request $request)
    {
        if ($request->headers->has(self::LINKS_HEADER)) {
            return $this->parseHeaderLinks($request->headers->get(self::LINKS_HEADER));

        } else {
            if ($request->request->has(self::LINKS_PROPERTY)) {
                return $this->parseBodyLinks($request->request->get(self::LINKS_PROPERTY));
            }
        }

        return array();
    }
}