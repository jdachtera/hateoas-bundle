<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 06.02.15
 * Time: 08:12
 */

namespace uebb\HateoasBundle\View;


use Symfony\Component\Routing\RouterInterface;
use uebb\HateoasBundle\Entity\ResourceInterface;

class ResourceView extends View
{
    /**
     * @param RouterInterface $router
     * @param array $validationErrors
     */
    public function __construct(RouterInterface $router, ResourceInterface $resource)
    {
        parent::__construct($router);

        //$contentType = 'application/' . strtolower(str_replace('\\', '.', get_class($resource))) . '+json';

        //$contentType = 'application/vnd.uebb.hateoas.resource+json';

        //$this->setHeader('Content-Type', $contentType);

        $this->setData($resource);


        /*

                // Disable the caching mechanism
                $ifModifiedSince = new \DateTime($request->headers->get('If-Modified-Since', '1900-01-01 00:00:00'));
                $lastModified = $resource->getUpdatedAt;

                $response = new Response();
                $response->setMaxAge(10);
                $response->headers->addCacheControlDirective('must-revalidate');
                $response->setLastModified(($lastModified ? $lastModified : new \DateTime()));


                $this->setResponse($response);

                if (!($lastModified && $ifModifiedSince) || $ifModifiedSince < $lastModified) {
                    $this->setData($resource);
                    $this->setHeader('Content-Type', $contentType);
                } else {
                    $this->setStatusCode(304);
                }

        */

    }
}