<?php

namespace uebb\HateoasBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;


interface LinkParserInterface
{
    /**
     * @param GetResponseEvent $event
     * @return array
     */
    public function parseLinks(Request $request);
}