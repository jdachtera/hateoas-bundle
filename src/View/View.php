<?php
namespace uebb\HateoasBundle\View;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 06.02.15
 * Time: 08:04
 */

class View extends \FOS\RestBundle\View\View {
    /**
     * @var RouterInterface
     */
    protected $router;


    public function __construct(RouterInterface $router)
    {

        $this->router = $router;
    }

    protected function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }
}