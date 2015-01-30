<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 29.01.15
 * Time: 18:46
 */

namespace uebb\HateoasBundle\Service;

use Doctrine\DBAL\Query\QueryBuilder;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class ResponseProcessor
{

    /**
     * @var RouterInterface
     */
    protected $router;

    protected function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }

    /**
     * Get an error view for resource validation errors
     *
     * @param array $validationErrors - The errors
     * @return View
     */
    public function getValidationErrorView($validationErrors)
    {
        $children = array();

        foreach ($validationErrors as $violation) {
            if (!isset($children[$violation->getPropertyPath()])) {
                $children[$violation->getPropertyPath()] = array('errors' => array());
            }
            $children[$violation->getPropertyPath()]['errors'][] = $violation->getMessage();
        }

        return new View(array(
            'code' => 400,
            'message' => 'Validation Failed',
            'errors' => array(
                'children' => $children
            )
        ), 400);
    }

    public function getResourceCreationView($resource)
    {
        $response = new Response();
        $response->setStatusCode(201);

        $routeName = 'get_' . strtolower(basename(get_class($resource)));

        // set the `Location` header when creating new resources
        $response->headers->set('Location', $this->generateUrl($routeName, array('id' => $resource->getId()), true));

        return $response;
    }


    /**
     * Get a View for a single resource
     *
     * @param Request $request - The http request
     * @param BaseEntity $resource - The resource
     * @return View
     */
    public function getResourceView(Request $request, BaseEntity $resource)
    {

        $contentType = 'application/' . strtolower(str_replace('\\', '.', get_class($resource))) . '+json';

        $view = new View();
        $view->setData($resource);
        $view->setHeader('Content-Type', $contentType);
        return $view;

        // Disable the caching mechanism
        $ifModifiedSince = new \DateTime($request->headers->get('If-Modified-Since', '1900-01-01 00:00:00'));
        $lastModified = $resource->getUpdated();

        $view = new View();

        $response = new Response();
        $response->setMaxAge(10);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setLastModified(($lastModified ? $lastModified : new \DateTime()));

        $view->setResponse($response);

        if (!($lastModified && $ifModifiedSince) || $ifModifiedSince < $lastModified) {
            $view->setData($resource);
            $view->setHeader('Content-Type', $contentType);
        } else {
            $view->setStatusCode(304);
        }
        return $view;
    }

    /**
     * Get a view for a resource collection
     *
     * @param Request $request - The http request
     * @param QueryBuilder $queryBuilder - The query builder for the collection
     * @return View
     */
    public function getResourceCollectionView(Request $request, QueryBuilder $queryBuilder)
    {
        $view = new View();

        $view->setData($this->getPagerfantaRepresentation($request, new DoctrineORMAdapter($queryBuilder)));
        $view->setHeader('Content-Type', 'application/vnd.uebb.hateoas.collection+json');


        /**
         * @var \DateTime $lastModified
         */
//        $lastModified = $this->getLastModifiedDateForQuery($queryBuilder);
//
//        $ifModifiedSince = $request->headers->get('If-Modified-Since', false);
//        if ($ifModifiedSince !== false) {
//            $ifModifiedSince = new \DateTime($ifModifiedSince);
//        }
//
//        $view = new View();
//
//        $response = new Response();
//        $response->setMaxAge(10);
//        $response->headers->addCacheControlDirective('must-revalidate');
//        $response->setLastModified(($lastModified ? $lastModified : new \DateTime()));
//
//        $view->setResponse($response);
//        if (true || !($lastModified && $ifModifiedSince) || $lastModified > $ifModifiedSince
//        ) {
//            $view->setData($this->getPagerfantaRepresentation(new DoctrineORMAdapter($queryBuilder)));
//            $view->setHeader('Content-Type', 'application/vnd.uebb.hateoas.collection+json');
//
//        } else {
//            $view->setStatusCode(304);
//        }
        return $view;
    }


    /**
     * Construct a pagerfanta representation from the current request
     *
     * @param AdapterInterface $adapter - The adapter to use
     * @return PaginatedRepresentation
     */
    protected function getPagerfantaRepresentation(Request $request, AdapterInterface $adapter)
    {
        $pagerfanta = new Pagerfanta($adapter);

        $limit = $request->query->get('limit');
        $zeroLimit = false;

        if (!$limit && ($limit === 0 || $limit === '0')) {
            $limit = $pagerfanta->count();
            $zeroLimit = true;
        }
        if (!$limit) {
            $limit = 10;
        }
        $pagerfanta->setMaxPerPage($limit);
        $page = $request->query->get('page');

        $nbPages = $pagerfanta->getNbPages();

        if (!$page) {
            $page = 1;
        }
        // Avoid errors: redirect to max page
        if ($page > $nbPages) {
            $page = $nbPages;
        }

        $pagerfanta->setCurrentPage($page);

        $route = new Route($request->get('_route'), $request->attributes->get('_route_params'), true);

        return new QueryablePaginatedRepresentation(
            new CollectionRepresentation($pagerfanta->getCurrentPageResults()),
            $route->getName(),
            $route->getParameters(),
            $pagerfanta->getCurrentPage(),
            $zeroLimit ? 0 : $pagerfanta->getMaxPerPage(),
            $nbPages,
            null,
            null,
            $route->isAbsolute(),
            $request->query->get('where'),
            $request->query->get('search'),
            $request->query->get('order'),
            $request->query->get('filter'),
            null,
            null,
            null,
            null
        );

    }
}