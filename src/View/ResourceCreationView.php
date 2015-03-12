<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 06.02.15
 * Time: 08:08
 */

namespace uebb\HateoasBundle\View;


use Symfony\Component\Routing\RouterInterface;
use uebb\HateoasBundle\Entity\ResourceInterface;

class ResourceCreationView extends View
{
    /**
     * @param RouterInterface $router
     * @param array $validationErrors
     */
    public function __construct(RouterInterface $router, ResourceInterface $resource, array $patch)
    {
        parent::__construct($router);

        $this->setStatusCode(201);
        $routeName = 'get_'.strtolower(basename(str_replace('\\', '//', get_class($resource))));

        // set the `Location` header when creating new resources
        $this->setHeader('Location', $this->generateUrl($routeName, array('id' => $resource->getId()), true));

        $this->setData($patch);
    }


}