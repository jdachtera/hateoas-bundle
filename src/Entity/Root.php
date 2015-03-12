<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 04.02.15
 * Time: 11:10
 */

namespace uebb\HateoasBundle\Entity;

use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation as Serializer;

/**
 * Class Root
 * @package uebb\HateoasBundle\Entity
 * @Hateoas\RelationProvider("uebb.hateoas.relation_provider:addRootRelations")
 *
 * @Serializer\ExclusionPolicy("all")
 */
class Root implements RootInterface
{

    /**
     * @var string
     */
    protected $prefix = '/';

    /**
     * @var \uebb\HateoasBundle\Entity\User
     */
    protected $currentUser;

    /**
     * @var array
     */
    protected $entityNames = array();

    /**
     * @var array
     * @Serializer\Expose
     */
    protected $validationRules;

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

    /**
     * @return array
     */
    public function getValidationRules()
    {
        return $this->validationRules;
    }

    /**
     * @param array $validationRules
     */
    public function setValidationRules($validationRules)
    {
        $this->validationRules = $validationRules;
    }

    /**
     * @return User
     */
    public function getCurrentUser()
    {
        return $this->currentUser;
    }

    /**
     * @param User $currentUser
     */
    public function setCurrentUser($currentUser)
    {
        $this->currentUser = $currentUser;
    }

    /**
     * @return array
     */
    public function getEntityNames()
    {
        return $this->entityNames;
    }

    /**
     * @param array $entityNames
     */
    public function setEntityNames($entityNames)
    {
        $this->entityNames = $entityNames;
    }



}