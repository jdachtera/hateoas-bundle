<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 05.05.14
 * Time: 10:52
 */

namespace uebb\HateoasBundle\Controller;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Core\SecurityContextInterface;
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
     * @var RequestProcessor
     */
    protected $requestProcessor;

    /**
     * @var ResponseProcessor
     */
    protected $responseProcessor;


    /**
     * Represents the collection of all resources of the entity type
     *
     * @param Request $request
     * @return \FOS\RestBundle\View\View
     */
    public function cgetAction(Request $request)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->requestProcessor->getResources($this->entityName, $request);
        return $this->responseProcessor->getResourceCollectionView($request, $queryBuilder);
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
        $resource = $this->requestProcessor->getResource($this->entityName, $id);
        return $this->responseProcessor->getResourceView($request, $resource);
    }

    /**
     * POST a new resource to the collection
     *
     * @param Request $request
     * @return \FOS\RestBundle\View\View|Response
     */
    public function postAction(Request $request)
    {
        $resource = $this->requestProcessor->createResource($this->entityName, $request);
        /** @var RecursiveValidator $validator */
        $validator = $this->get('validator');
        $validationErrors = $validator->validate($resource);
        if ($validationErrors->count() > 0) {
            return $this->responseProcessor->validationErrorView($validationErrors);
        } else {
            return $this->responseProcessor->getResourceCreationView($resource);
        }
    }

    /**
     * DELETE a single resource. Soft delete (via deleted property) is not implemented at the moment. Resources are removed completely from the database
     *
     * @Annotations\View(statusCode=204)
     *
     * @param Request $request - The request
     * @param integer $id - The id of the resource
     * @return \FOS\RestBundle\View\View
     */
    public function deleteAction(Request $request, $id)
    {
        $this->requestProcessor->removeResource($this->entityName, $id);
    }


    /**
     * PATCH a resource. The patch must be in the request body and in the JSON-Patch format
     *
     * @param Request $request - The http request
     * @param integer $id - The id of the resource
     */
    public function patchAction(Request $request, $id)
    {
        $resource = $this->requestProcessor->getResource($this->entityName, $id);
        $actions = $request->request->all();

        $this->requestProcessor->patchResource($this->entityName, $resource, $actions);
        $this->patchResource($resource, $actions);
    }


}