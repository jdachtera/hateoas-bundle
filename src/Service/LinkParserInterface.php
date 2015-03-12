<?php

namespace uebb\HateoasBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;


interface LinkParserInterface
{
    /**
     * @param GetResponseEvent $event
     * @return array
     */
    public function parseRequestLinks(Request $request);
}