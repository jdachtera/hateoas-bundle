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
class Root implements RootInterface
{

    /**
     * @var string
     */
    protected $prefix;

    public function __construct($prefix = '/')
    {
        $this->prefix = $prefix;
    }

    /**
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }


}