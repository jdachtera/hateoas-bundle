<?php

namespace uebb\HateoasBundle\Service;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

interface QueryParserInterface
{
    /**
     * @param $entityName
     * @param QueryBuilder $queryBuilder
     * @param $where
     * @return QueryBuilder
     */
    public function applyWhere($entityName, QueryBuilder $queryBuilder, $where, $maxDepth);

    public function deepJoinProperties($entityName, QueryBuilder $queryBuilder, $propertyParts, $joinedAliases = array(), $maxDepth = TRUE);

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
    public function applyQueryParameters($entityName, Request $request, QueryBuilder $queryBuilder, $maxDepth = TRUE);
}