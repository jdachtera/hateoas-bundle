<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 11.02.15
 * Time: 17:50
 */

namespace uebb\HateoasBundle\Event;


abstract class ActionEventData
{
    /**
     * @var string
     */
    protected $entityName;

    /**
     * @param $entityName
     */
    public function __construct($entityName) {
        $this->entityName = $entityName;
    }
    /**
     * @return string
     */
    public function getEntityName()
    {
        return $this->entityName;
    }


}