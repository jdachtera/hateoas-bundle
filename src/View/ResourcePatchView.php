<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 06.02.15
 * Time: 08:25
 */

namespace uebb\HateoasBundle\View;


use Symfony\Component\Routing\RouterInterface;
use uebb\HateoasBundle\Entity\ResourceInterface;

class ResourcePatchView extends View
{
    public function __construct(RouterInterface $router, ResourceInterface $resource)
    {
        parent::__construct($router);
        $this->setStatusCode(204);
    }

}