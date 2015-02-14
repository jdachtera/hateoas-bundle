<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 11.02.15
 * Time: 17:54
 */

namespace uebb\HateoasBundle\Event;


use Doctrine\ORM\QueryBuilder;
use uebb\HateoasBundle\Entity\ResourceInterface;

class GetLinkCollectionActionEventData extends ActionEventData
{
    /**
     * @var ResourceInterface
     */
    protected $resource;

    /**
     * @var mixed
     */
    protected $linkRelation;

    /**
     * @var mixed
     */
    protected $queryBuilder;

    /**
     * @param $entityName
     * @param ResourceInterface $resource
     * @param $linkRelation
     * @param QueryBuilder $queryBuilder
     */
    public function __construct($entityName, ResourceInterface $resource, $linkRelation, QueryBuilder $queryBuilder = NULL)
    {
        parent::__construct($entityName);
        $this->resource = $resource;
        $this->linkRelation = $linkRelation;
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * @return ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @return mixed
     */
    public function getLinkRelation()
    {
        return $this->linkRelation;

    }

    /**
     * @return mixed
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

}