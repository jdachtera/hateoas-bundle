<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 01.02.15
 * Time: 19:08
 */

namespace uebb\HateoasBundle\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class SoftDeleteFilter extends SQLFilter {
    /**
     * Gets the SQL query part to add to a query.
     *
     * @param ClassMetaData $targetEntity
     * @param string $targetTableAlias
     *
     * @return string The constraint SQL if there is available, empty string otherwise.
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        if ($targetEntity->hasField('deletedAt')) {
            $now = date('Y-m-d H:i:s');
            return "{$targetTableAlias}.deletedAt IS NULL OR {$targetTableAlias}.deletedAt > '{$now}'";

        }

        return '';
    }

}