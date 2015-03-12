<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 06.02.15
 * Time: 08:25
 */

namespace uebb\HateoasBundle\View;


use Symfony\Component\Routing\RouterInterface;

class ResourceDeleteView extends View
{
    public function __construct(RouterInterface $router)
    {
        parent::__construct($router);
        $this->setStatusCode(204);
    }

}