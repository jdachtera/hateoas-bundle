<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 05.05.14
 * Time: 10:52
 */

namespace uebb\HateoasBundle\Controller;

use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use uebb\HateoasBundle\Service\RequestProcessor;
use uebb\HateoasBundle\Service\ResponseProcessor;


/**
 * Class DoctrineRestCRUDController
 *
 * The base class for HATEOAS style api endpoints. Provides CRUD actions and should be extended for each resource
 *
 * @package uebb\HateoasBundle\Controller
 */
class HateoasController extends FOSRestController implements ClassResourceInterface
{
    /**
     * The entity to manage
     *
     * @var string
     */
    protected $entityName;

    /**
     * @return RequestProcessor
     */
    protected function getRequestProcessor()
    {
        return $this->get('uebb.hateoas.request_processor');
    }

    /**
     * @return ResponseProcessor
     */
    protected function getResponseProcessor()
    {
        return $this->get('uebb.hateoas.response_processor');
    }

    /**
     * Represents the collection of all resources of the entity type
     *
     * @param Request $request
     * @return \FOS\RestBundle\View\View
     */
    public function cgetAction(Request $request)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->getRequestProcessor()->getResources($this->entityName, $request);

        return $this->getResponseProcessor()->getResourceCollectionView($request, $queryBuilder);
    }

    /**
     * Represents a single resource
     *
     * @param $id
     * @param Request $request
     * @return View
     */
    public function getAction($id, Request $request)
    {
        $resource = $this->getRequestProcessor()->getResource($this->entityName, $id);
        return $this->getResponseProcessor()->getResourceView($request, $resource);
    }

    /**
     * @param $id
     * @param $rel
     * @param Request $request
     * @return View
     */
    public function getLinksAction($id, $rel, Request $request)
    {
        $resource = $this->getRequestProcessor()->getResource($this->entityName, $id);
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->getRequestProcessor()->getRelatedResources($this->entityName, $request, $resource, $rel);
        return $this->getResponseProcessor()->getResourceCollectionView($request, $queryBuilder);
    }

    /**
     * POST a new resource to the collection
     *
     * @param Request $request
     * @return \FOS\RestBundle\View\View|Response
     */
    public function postAction(Request $request)
    {
        $resource = $this->getRequestProcessor()->createResource($this->entityName, $request);
        /** @var RecursiveValidator $validator */
        $validator = $this->get('validator');
        $validationErrors = $validator->validate($resource);
        if ($validationErrors->count() > 0) {
            return $this->getResponseProcessor()->validationErrorView($validationErrors);
        } else {
            $this->getDoctrine()->getManager()->persist($resource);
            $this->getDoctrine()->getManager()->flush();
            return $this->getResponseProcessor()->getResourceCreationView($resource);
        }
    }

    /**
     * DELETE a single resource.
     *
     * @Annotations\View(statusCode=204)
     *
     * @param Request $request - The request
     * @param integer $id - The id of the resource
     * @return \FOS\RestBundle\View\View
     */
    public function deleteAction(Request $request, $id)
    {
        $this->getRequestProcessor()->removeResource($this->entityName, $id);
    }


    /**
     * PATCH a resource. The patch must be in the request body and in the JSON-Patch format
     *
     * @param Request $request - The http request
     * @param integer $id - The id of the resource
     */
    public function patchAction(Request $request, $id)
    {
        $resource = $this->getRequestProcessor()->getResource($this->entityName, $id);
        $actions = $request->request->all();

        $this->getRequestProcessor()->patchResource($this->entityName, $resource, $actions);
        $this->patchResource($resource, $actions);
    }


}