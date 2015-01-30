<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 06.06.14
 * Time: 09:11
 */

namespace uebb\HateoasBundle\Representation;


use Hateoas\Configuration\Annotation as Hateoas;
use Hateoas\Representation\PaginatedRepresentation;
use JMS\Serializer\Annotation as Serializer;

/**
 * Class QueryablePaginatedRepresentation
 *
 * Represents a queryable and paginated collection of hateoas resources
 *
 * @package uebb\HateoasBundle\Service
 *
 * @Serializer\ExclusionPolicy("all")
 */
class QueryablePaginatedRepresentation extends PaginatedRepresentation
{
    /**
     * @var string
     *
     * @Serializer\Expose
     * @Serializer\XmlAttribute
     */
    private $search;
    /**
     * @var string
     *
     * @Serializer\Expose
     * @Serializer\XmlAttribute
     */
    private $where;
    /**
     * @var string
     *
     * @Serializer\Expose
     * @Serializer\XmlAttribute
     */
    private $order;
    /**
     * @var array
     *
     * @Serializer\Expose
     * @Serializer\XmlAttribute
     */
    private $filter;
    /**
     * @var string
     */
    private $whereParameterName;
    /**
     * @var string
     */
    private $searchParameterName;
    /**
     * @var string
     */
    private $orderParameterName;
    /**
     * @var string
     */
    private $filterParameterName;

    public function __construct(
        $inline,
        $route,
        array $parameters = array(),
        $page,
        $limit,
        $pages,
        $pageParameterName = null,
        $limitParameterName = null,
        $absolute = false,
        $where,
        $search,
        $order,
        $filter,
        $whereParameterName = null,
        $searchParameterName = null,
        $orderParameterName = null,
        $filterParameterName = null
    )
    {
        parent::__construct($inline, $route, $parameters, $page, $limit, $pages, $pageParameterName, $limitParameterName, $absolute);
        $this->where = $where;
        $this->whereParameterName = $whereParameterName ?: 'where';

        $this->search = $search;
        $this->searchParameterName = $whereParameterName ?: 'search';

        $this->order = $order;
        $this->orderParameterName = $orderParameterName ?: 'order';

        $this->filter = $filter;
        $this->filterParameterName = $filterParameterName ?: 'filter';
    }

    /**
     * @param  null $page
     * @param  null $limit
     * @return array
     */
    public function getParameters($page = null, $limit = null, $where = null, $search = null, $order = null, $filter = null)
    {
        $parameters = parent::getParameters($page, $limit);
        $parameters[$this->whereParameterName] = null == $where ? $this->getWhere() : $where;
        $parameters[$this->searchParameterName] = null == $search ? $this->getSearch() : $search;
        $parameters[$this->orderParameterName] = null == $order ? $this->getOrder() : $order;
        $parameters[$this->filterParameterName] = null == $filter ? $this->getFilter() : $filter;
        return $parameters;
    }

    /**
     * @return string
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * @return string
     */
    public function getSearchParameterName()
    {
        return $this->searchParameterName;
    }

    /**
     * @return string
     */
    public function getWhere()
    {
        return $this->where;
    }

    /**
     * @return string
     */
    public function getWhereParameterName()
    {
        return $this->whereParameterName;
    }

    /**
     * @return string
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return string
     */
    public function getOrderParameterName()
    {
        return $this->orderParameterName;
    }

    /**
     * @param mixed $filter
     */
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    /**
     * @return mixed
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param string $filterParameterName
     */
    public function setFilterParameterName($filterParameterName)
    {
        $this->filterParameterName = $filterParameterName;
    }

    /**
     * @return string
     */
    public function getFilterParameterName()
    {
        return $this->filterParameterName;
    }


}