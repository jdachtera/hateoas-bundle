<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 30.01.15
 * Time: 10:13
 */

namespace uebb\HateoasBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation as Serializer;
use Knp\DoctrineBehaviors\Model as ORMBehaviors;

/**
 * Class Resource
 *
 * @ORM\MappedSuperclass
 * @Serializer\ExclusionPolicy("all")
 * @package uebb\HateoasBundle\Entity
 */
class Resource implements ResourceInterface
{

    use ORMBehaviors\Timestampable\Timestampable,
        ORMBehaviors\SoftDeletable\SoftDeletable,
        ORMBehaviors\Blameable\Blameable;

    public static function getQueryAbleProperties() {
        return array('id');
    }

    /**
     * @var string
     *
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     *
     * @Serializer\Expose
     */
    protected $id;

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

}