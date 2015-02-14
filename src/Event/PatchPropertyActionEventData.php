<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 11.02.15
 * Time: 17:54
 */

namespace uebb\HateoasBundle\Event;


use uebb\HateoasBundle\Entity\ResourceInterface;

class PatchPropertyActionEventData extends ActionEventData
{
    /**
     * @var ResourceInterface
     */
    protected $resource;

    /**
     * @var mixed
     */
    protected $propertyName;

    /**
     * @var mixed
     */
    protected $propertyValue;


    /**
     * @param $entityName
     * @param ResourceInterface $resource
     * @param $propertyName
     * @param $propertyValue
     */
    public function __construct($entityName, ResourceInterface $resource, $propertyName, $propertyValue)
    {
        parent::__construct($entityName);
        $this->resource = $resource;
        $this->propertyName = $propertyName;
        $this->propertyValue = $propertyValue;
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

    /**
     * @return mixed
     */
    public function getPropertyName()
    {
        return $this->propertyName;
    }

    /**
     * @return mixed
     */
    public function getPropertyValue()
    {
        return $this->propertyValue;
    }


}