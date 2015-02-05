<?php
namespace uebb\HateoasBundle\Controller;

/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 04.02.15
 * Time: 11:11
 */

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use uebb\HateoasBundle\Entity\Root;

/**
 * Class RootController
 *
 */
class RootController extends \FOS\RestBundle\Controller\FOSRestController
{
    /**
     * @return View
     *
     */
    public function getRootAction() {
        return $this->view(new Root());
    }
}