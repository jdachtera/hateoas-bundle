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
use Symfony\Component\Validator\Validator\RecursiveValidator;
use uebb\HateoasBundle\Entity\Root;
use FOS\RestBundle\Controller\Annotations\Get;


/**
 * Class RootController
 *
 */
class RootController extends \FOS\RestBundle\Controller\FOSRestController
{
    protected $entityNames = array();

    /**
     * @Get("/")
     *
     */
    public function getRootAction()
    {
        $root = new Root();

        $root->setPrefix(substr(
            $this->container->get('router')->generate($this->container->get('request')->get('_route'), array(), false),
            strlen($this->container->get('request')->getBasePath())
        ));

        $root->setEntityNames($this->entityNames);

        return $this->view($root);
    }

    /**
     * @param $rel
     * @return string
     * @Get("/schemas/{rel}")
     */
    public function getRootSchemaAction($rel)
    {
        if (isset($this->entityNames[$rel])) {
            $className = $this->getDoctrine()->getManager()->getClassMetadata($this->entityNames[$rel])->getName();
            return
                $this->view($this->get('json_schema.response.factory')->create($className));
        }
    }
}