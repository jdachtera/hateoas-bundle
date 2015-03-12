<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 06.02.15
 * Time: 08:15
 */

namespace uebb\HateoasBundle\View;


use Doctrine\ORM\QueryBuilder;
use Hateoas\Configuration\Route;
use Hateoas\Representation\CollectionRepresentation;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use uebb\HateoasBundle\Representation\QueryablePaginatedRepresentation;

class ResourceCollectionView extends View
{
    /**
     * Get a view for a resource collection
     *
     * @param Request $request - The http request
     * @param QueryBuilder $queryBuilder - The query builder for the collection
     * @return View
     */
    public function __construct(RouterInterface $router, Request $request, QueryBuilder $queryBuilder)
    {
        parent::__construct($router);

        $this->setData($this->getPagerfantaRepresentation($request, new DoctrineORMAdapter($queryBuilder)));
        //$this->setHeader('Content-Type', 'application/vnd.uebb.hateoas.collection+json');


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
//        $response = new Response();
//        $response->setMaxAge(10);
//        $response->headers->addCacheControlDirective('must-revalidate');
//        $response->setLastModified(($lastModified ? $lastModified : new \DateTime()));
//
//        $this->setResponse($response);
//        if (true || !($lastModified && $ifModifiedSince) || $lastModified > $ifModifiedSince
//        ) {
//            $this->setData($this->getPagerfantaRepresentation(new DoctrineORMAdapter($queryBuilder)));
//            $this->setHeader('Content-Type', 'application/vnd.uebb.hateoas.collection+json');
//
//        } else {
//            $this->setStatusCode(304);
//        }
        return $this;
    }


    /**
     * Construct a pagerfanta representation from the current request
     *
     * @param AdapterInterface $adapter - The adapter to use
     * @return QueryablePaginatedRepresentation
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

        $route = new Route($request->get('_route'), $request->attributes->get('_route_params'), false);

        return new QueryablePaginatedRepresentation(
            new CollectionRepresentation($pagerfanta->getCurrentPageResults()),
            $route->getName(),
            $route->getParameters(),
            $pagerfanta->getCurrentPage(),
            $zeroLimit ? 0 : $pagerfanta->getMaxPerPage(),
            $nbPages,
            $pagerfanta->count(),
            null,
            null,
            $route->isAbsolute(),
            $request->query->get('where'),
            $request->query->get('search'),
            $request->query->get('order'),
            null,
            null,
            null
        );

    }
}