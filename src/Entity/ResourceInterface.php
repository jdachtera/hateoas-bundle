<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 15.05.14
 * Time: 11:35
 */

namespace uebb\HateoasBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * Class BaseEntity
 *
 * This is the base entity interface for Hateoas resources. New entities should implement it
 *
 * @ORM\MappedSuperclass
 *
 * @package uebb\HateoasBundle\Entity
 */
interface ResourceInterface
{

    /**
     * return string
     */
    public function getId();

    /**
     * @return \DateTime
     */
    public function getCreatedAt();

    /**
     * @return \DateTime
     */
    public function getUpdatedAt();

    /**
     * @return boolean
     */
    public function isDeleted();

    /**
     * @return UserInterface
     */
    public function getCreatedBy();

    /**
     * @return UserInterface
     */
    public function getUpdatedBy();
} 