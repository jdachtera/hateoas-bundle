<?php
namespace uebb\HateoasBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Hateoas\Configuration\Annotation as Hateoas;
use JMS\Serializer\Annotation as Serializer;
use uebb\HateoasBundle\Annotation as UebbHateoas;
use uebb\HateoasBundle\Entity\Resource;

/**
 * Class TestPerson
 * @ORM\Entity
 * @Hateoas\RelationProvider("uebb.hateoas.relation_provider")
 */
class TestPerson extends Resource
{
    /**
     * @var string
     * @ORM\Column(type="string")
     * @Serializer\Expose
     * @UebbHateoas\FormField
     */
    protected $name;

    /**
     * @var \DateTime
     * @ORM\Column(name="birthday", type="date", nullable=true)
     * @Serializer\Expose
     * @UebbHateoas\FormField(type="date", options={"widget"="single_text", "format"="yyyy-MM-dd"})
     */
    protected $birthday;

    /**
     * @var ArrayCollection
     * @ORM\ManyToMany(targetEntity="TestPerson", inversedBy="children")
     *
     */
    protected $parents;

    /**
     * @var ArrayCollection
     * @ORM\ManyToMany(targetEntity="TestPerson", mappedBy="parent")
     */
    protected $children;

    /**
     * @var TestPerson
     * @ORM\ManyToOne(targetEntity="TestPerson", inversedBy="employees")
     */
    protected $employer;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="TestPerson", mappedBy="employer")
     */
    protected $employees;

    public function __construct()
    {
        $this->parents = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->employees = new ArrayCollection();
    }

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
     * @return ArrayCollection
     */
    public function getParents()
    {
        return $this->parents;
    }

    /**
     * @param ArrayCollection $parents
     */
    public function setParents($parents)
    {
        $this->parents = $parents;
    }


    /**
     * @return ArrayCollection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param ArrayCollection $children
     */
    public function setChildren($children)
    {
        $this->children = $children;
    }

    /**
     * @return TestPerson
     */
    public function getEmployer()
    {
        return $this->employer;
    }

    /**
     * @param TestPerson $employer
     */
    public function setEmployer($employer)
    {
        $this->employer = $employer;
    }

    /**
     * @return ArrayCollection
     */
    public function getEmployees()
    {
        return $this->employees;
    }

    /**
     * @param ArrayCollection $employees
     */
    public function setEmployees($employees)
    {
        $this->employees = $employees;
    }

    /**
     * @return \DateTime
     */
    public function getBirthday()
    {
        return $this->birthday;
    }

    /**
     * @param \DateTime $birthday
     */
    public function setBirthday($birthday)
    {
        $this->birthday = $birthday;
    }


}