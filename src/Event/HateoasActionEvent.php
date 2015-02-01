<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 20.05.14
 * Time: 12:17
 */

namespace uebb\HateoasBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use uebb\HateoasBundle\Entity\ResourceInterface;

/**
 * Class EntityVoteData
 *
 * This represents the data for a security voter request
 *
 * @package uebb\HateoasBundle\Security\Authorization\Voter
 */
class HateoasActionEvent extends Event
{

    const BEFORE = 'before';

    const AFTER = 'after';

    /**
     * @var string
     */
    protected $method;
    /**
     * @var ResourceInterface
     */
    protected $entity;
    /**
     * @var string
     */
    protected $resource;
    /**
     * @var string
     */
    protected $propertyName;
    /**
     * @var ResourceInterface
     */
    protected $propertyValue;

    /**
     * @param string $method
     * @param $entity
     * @param ResourceInterface $resource
     * @param string $propertyName
     * @param ResourceInterface $propertyValue
     * @param Request $request
     */
    public function __construct($method, $entity, ResourceInterface $resource = NULL, $propertyName = NULL, $propertyValue = NULL)
    {
        $this->method = $method;
        $this->resource = $resource;
        $this->entity = $entity;
        $this->propertyName = $propertyName;
        $this->propertyValue = $propertyValue;

        if (!($resource === NULL || $resource instanceof $entity)) {
            throw new \Symfony\Component\Security\Core\Exception\InvalidArgumentException();
        }

        // Validate arguments
        switch ($method) {
            case 'link':
            case 'unlink':
                if ($propertyName === NULL || !($propertyValue instanceof ResourceInterface)) {
                    throw new \Symfony\Component\Security\Core\Exception\InvalidArgumentException();
                }
                break;
            case 'patchProperty':
            case 'lget':
                if ($propertyName === NULL) {
                    throw new \Symfony\Component\Security\Core\Exception\InvalidArgumentException();
                }
                break;
            case 'post':
            case 'put':
            case 'patch':
            case 'delete':
            case 'get':
            case 'cget':
                break;
            default:
                throw new \Symfony\Component\Security\Core\Exception\InvalidArgumentException();
                break;
        }
    }

    /**
     * @return \uebb\HateoasBundle\Entity\ResourceInterface
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return string
     */
    public function getPropertyName()
    {
        return $this->propertyName;
    }

    /**
     * @return \uebb\HateoasBundle\Entity\ResourceInterface
     */
    public function getPropertyValue()
    {
        return $this->propertyValue;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getResource()
    {
        return $this->resource;
    }

} 