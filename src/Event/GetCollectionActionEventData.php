<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 11.02.15
 * Time: 17:54
 */

namespace uebb\HateoasBundle\Event;


use Doctrine\ORM\QueryBuilder;

class GetCollectionActionEventData extends ActionEventData
{
    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @param $entityName
     * @param QueryBuilder $queryBuilder
     */
    public function __construct($entityName, QueryBuilder $queryBuilder = null)
    {
        parent::__construct($entityName);
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * @return QueryBuilder|NULL
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

}