<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 11.02.15
 * Time: 17:54
 */

namespace uebb\HateoasBundle\Event;


use uebb\HateoasBundle\Entity\ResourceInterface;

class RemoveLinkActionEventData extends ActionEventData
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
    protected $linkResource;


    /**
     * @param $entityName
     * @param ResourceInterface $resource
     * @param $linkRelation
     * @param ResourceInterface $linkResource
     */
    public function __construct($entityName, ResourceInterface $resource, $linkRelation, ResourceInterface $linkResource)
    {
        parent::__construct($entityName);
        $this->resource = $resource;
        $this->linkRelation = $linkRelation;
        $this->linkResource = $linkResource;
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
    public function getLinkResource()
    {
        return $this->linkResource;
    }

}