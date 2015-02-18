<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 05.05.14
 * Time: 10:52
 */

namespace uebb\HateoasBundle\Controller;

use Doctrine\Common\Util\Debug;
use Doctrine\ORM\QueryBuilder;
use FOS\RestBundle\Controller\Annotations;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use uebb\HateoasBundle\Entity\File;
use uebb\HateoasBundle\Event\ActionEvent;
use uebb\HateoasBundle\Event\PatchActionEventData;
use uebb\HateoasBundle\Event\PostActionEventData;
use uebb\HateoasBundle\Event\RemoveActionEventData;
use uebb\HateoasBundle\Service\RequestProcessor;
use uebb\HateoasBundle\Service\ResponseProcessor;
use uebb\HateoasBundle\View\FileDownloadView;
use uebb\HateoasBundle\View\ImageDownloadView;
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
     * @return RecursiveValidator
     */
    protected function getValidator()
    {
        return $this->get('validator');
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

        return new ResourceView($this->get('router'), $resource);
    }

    /**
     * Represents a single resource
     *
     * @param $id
     * @param Request $request
     * @return Response
     */
    public function getDownloadAction($id, Request $request)
    {
        $resource = $this->getRequestProcessor()->getResource($this->entityName, $id);
        if ($resource instanceof File) {

            $filename = $resource->getFullPath($this->container->getParameter('uebb.hateoas.upload_dir'));

            if (in_array($resource->getMimeType(), array('image/jpeg', 'image/png', 'image/gif'))) {

                $filename = $this->get('uebb.hateoas.image_resizer')->resizeImage(
                    $resource,
                    intval($request->query->get('width', '0'), 10),
                    intval($request->query->get('height', '0'), 10),
                    10,
                    $request->query->get('format', 'png')
                );

            }

            $displayName = pathinfo($resource->getName(), PATHINFO_BASENAME) . '.' . pathinfo($filename, PATHINFO_EXTENSION);

            return (new FileDownloadView($filename, $displayName, $request, FALSE))->getResponse();

        } else {
            throw new MethodNotAllowedHttpException(array());
        }
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
        $validationErrors = $this->getValidator()->validate($resource);

        if ($validationErrors->count() > 0) {
            return new ValidationErrorView($this->get('router'), $validationErrors);
        } else {
            $this->getDoctrine()->getManager()->persist($resource);
            $this->getDoctrine()->getManager()->flush();

            $this->getRequestProcessor()->dispatchActionEvent(new ActionEvent(ActionEvent::PERSIST, new PatchActionEventData($this->entityName, $resource)));

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
        $validationErrors = $this->getValidator()->validate($resource);

        if ($validationErrors->count() > 0) {
            return new ValidationErrorView($this->get('router'), $validationErrors);
        } else {
            $this->getDoctrine()->getManager()->persist($resource);
            $this->getDoctrine()->getManager()->flush();

            $this->getRequestProcessor()->dispatchActionEvent(new ActionEvent(ActionEvent::PERSIST, new PostActionEventData($this->entityName, $resource)));

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
        $resource = $this->getRequestProcessor()->removeResource($this->entityName, $id);

        $this->getDoctrine()->getManager()->flush();

        $this->getRequestProcessor()->dispatchActionEvent(new ActionEvent(ActionEvent::PERSIST, new RemoveActionEventData($this->entityName, $resource)));

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

        $validationErrors = $this->getValidator()->validate($resource);

        if ($validationErrors->count() > 0) {
            return new ValidationErrorView($this->get('router'), $validationErrors);
        } else {
            $this->getDoctrine()->getManager()->persist($resource);
            $this->getDoctrine()->getManager()->flush();

            $this->getRequestProcessor()->dispatchActionEvent(new ActionEvent(ActionEvent::PERSIST, new PatchActionEventData($this->entityName, $resource)));
            return new ResourcePatchView($this->get('router'), $resource);
        }
    }

}