<?php

namespace uebb\HateoasBundle\Service;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

interface QueryParserInterface
{
    /**
     * @param $entityName
     * @param QueryBuilder $queryBuilder
     * @param $where
     * @return QueryBuilder
     */
    public function applyWhere($entityName, QueryBuilder $queryBuilder, $where, $maxDepth);

    public function deepJoinProperties(
        $entityName,
        QueryBuilder $queryBuilder,
        $propertyParts,
        $joinedAliases = array(),
        $maxDepth = true
    );

    /**
     * @param $entityName
     * @param QueryBuilder $queryBuilder
     * @param $order
     * @return QueryBuilder
     */
    public function applyOrder($entityName, QueryBuilder $queryBuilder, $order, $joinedAliases = array(), $maxDepth);

    /**
     * @param $entityName
     * @param Request $request
     * @param QueryBuilder $queryBuilder
     * @param bool $maxDepth
     * @return QueryBuilder
     */
    public function applyQueryParameters($entityName, Request $request, QueryBuilder $queryBuilder, $maxDepth = true);
}