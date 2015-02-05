<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 04.02.15
 * Time: 11:10
 */

namespace uebb\HateoasBundle\Entity;

use JMS\Serializer\Annotation as Serializer;
use Hateoas\Configuration\Annotation as Hateoas;

/**
 * Class Root
 * @package uebb\HateoasBundle\Entity
 * @Hateoas\RelationProvider("uebb.hateoas.relation_provider:addRelations")
 *
 * @Serializer\ExclusionPolicy("all")
 */
class Root implements RootInterface {

}