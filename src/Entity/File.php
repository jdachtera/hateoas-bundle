<?php
namespace uebb\HateoasBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation as Serializer;
use Knp\DoctrineBehaviors\Model as ORMBehaviors;
use uebb\HateoasBundle\Annotation as UebbHateoas;


/**
 * Class File
 * @ORM\MappedSuperclass
 * @Serializer\ExclusionPolicy("all")
 */
class File extends Resource
{
    /**
     * @var string
     *
     * @Serializer\Expose
     */
    protected $name;
    /**
     * @var integer
     *
     * @Serializer\Expose
     */
    protected $size;
    /**
     * @var string
     *
     * @Serializer\Expose
     */
    protected $mimeType;
    /**
     * @var \Symfony\Component\HttpFoundation\File\UploadedFile
     */
    protected $upload;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * @return string
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * @param string $mimeType
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\File\UploadedFile
     */
    public function getUpload()
    {
        return $this->upload;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\File\UploadedFile $upload
     */
    public function setUpload($upload)
    {
        $this->upload = $upload;
    }
}
