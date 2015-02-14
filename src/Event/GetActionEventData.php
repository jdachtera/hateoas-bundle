<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 11.02.15
 * Time: 17:54
 */

namespace uebb\HateoasBundle\Event;


use uebb\HateoasBundle\Entity\ResourceInterface;

class GetActionEventData extends ActionEventData
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var ResourceInterface
     */
    protected $resource;

    /**
     * @param $entityName
     * @param $id
     * @param ResourceInterface $resource
     */
    public function __construct($entityName, $id, ResourceInterface $resource = NULL)
    {
        parent::__construct($entityName);
        $this->id = $id;
        $this->resource = $resource;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }
}