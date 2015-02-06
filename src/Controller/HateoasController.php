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
use uebb\HateoasBundle\View\ResourceCollectionView;
use uebb\HateoasBundle\View\ResourceCreationView;
use uebb\HateoasBundle\View\ResourceDeleteView;
use uebb\HateoasBundle\View\ResourcePatchView;
use uebb\HateoasBundle\View\ResourceView;
use uebb\HateoasBundle\View\ValidationErrorView;


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

        return new ResourceCollectionView($this->get('router'), $request, $queryBuilder);
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

        return new ResourceView($this->get('router'), $request);
    }

    /**
     * @param int $id
     * @param string $rel
     * @param Request $request
     * @return View
     */
    public function getLinkcollection($id, $rel, Request $request)
    {
        $resource = $this->getRequestProcessor()->getResource($this->entityName, $id);
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->getRequestProcessor()->getRelatedResources($this->entityName, $request, $resource, $rel);

        return new ResourceCollectionView($this->get('router'), $request, $queryBuilder);
    }

    public function patchLinkCollection($id, $rel, Request $request)
    {
        $resource = $this->getRequestProcessor()->getResource($this->entityName, $id);
        $this->getRequestProcessor()->patchResourceCollection($this->entityName, $resource, $rel, $request->request->all());
        $validationErrors = $this->get('validator')->validate($resource);

        if ($validationErrors->count() > 0) {
            return new ValidationErrorView($this->get('router'), $validationErrors);
        } else {
            $this->getDoctrine()->getManager()->persist($resource);
            $this->getDoctrine()->getManager()->flush();
            return new ResourcePatchView($this->get('router'), $resource);
        }
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
            return new ValidationErrorView($this->get('router'), $validationErrors);
        } else {
            $this->getDoctrine()->getManager()->persist($resource);
            $this->getDoctrine()->getManager()->flush();
            return new ResourceCreationView($this->get('router'), $resource);
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
        return new ResourceDeleteView($this->get('router'));
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
        $this->getRequestProcessor()->patchResource($this->entityName, $resource, $request->request->all());

        $validationErrors = $this->get('validator')->validate($resource);

        if ($validationErrors->count() === 0) {
            $this->getDoctrine()->getManager()->persist($resource);
            $this->getDoctrine()->getManager()->flush();
            return new ResourcePatchView($this->get('router'), $resource);
        } else {
            return new ValidationErrorView($this->get('router'), $validationErrors);
        }
    }

}