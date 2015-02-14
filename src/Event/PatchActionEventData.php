<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 11.02.15
 * Time: 17:54
 */

namespace uebb\HateoasBundle\Event;


use uebb\HateoasBundle\Entity\ResourceInterface;

class PatchActionEventData extends ActionEventData
{
    /**
     * @var ResourceInterface
     */
    protected $resource;


    /**
     * @param $entityName
     * @param ResourceInterface $resource
     */
    public function __construct($entityName, ResourceInterface $resource)
    {
        parent::__construct($entityName);
        $this->resource = $resource;
    }

    /**
     * @return ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }


}