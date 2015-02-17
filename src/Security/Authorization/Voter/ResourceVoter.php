<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 14.05.14
 * Time: 10:07
 */

namespace uebb\HateoasBundle\Security\Authorization\Voter;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use uebb\HateoasBundle\Event\ActionEvent;
use uebb\HateoasBundle\Event\AddLinkActionEventData;
use uebb\HateoasBundle\Event\GetActionEventData;
use uebb\HateoasBundle\Event\GetCollectionActionEventData;
use uebb\HateoasBundle\Event\GetLinkCollectionActionEventData;
use uebb\HateoasBundle\Event\PatchActionEventData;
use uebb\HateoasBundle\Event\PatchPropertyActionEventData;
use uebb\HateoasBundle\Event\PostActionEventData;
use uebb\HateoasBundle\Event\RemoveActionEventData;
use uebb\HateoasBundle\Event\RemoveLinkActionEventData;

/**
 * Class EntityVoter
 *
 * A Base class for HATEOAS resource voters
 *
 * @package uebb\HateoasBundle\Security\Authorization\Voter
 */
abstract class ResourceVoter implements VoterInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var array
     */
    protected $supportedClasses = array();
    /**
     * @var RoleHierarchyInterface
     */
    protected $roleHierarchy;


    /**
     * @param string $class
     * @return bool
     */
    public function supportsClass($class)
    {
        foreach ($this->supportedClasses as $supportedClass) {
            if ($supportedClass === $class || is_subclass_of($class, $supportedClass)) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * @param mixed $roleHierarchy
     */
    public function setRoleHierarchy(RoleHierarchyInterface $roleHierarchy)
    {
        $this->roleHierarchy = $roleHierarchy;
    }


    /**
     * @param string $attribute
     * @return bool
     */
    public function supportsAttribute($attribute)
    {
        return $attribute === 'RESOURCE_VOTE';
    }

    /**
     * @param TokenInterface $token
     * @param object $object
     * @param array $attributes
     * @return int
     * @throws \Symfony\Component\Security\Core\Exception\InvalidArgumentException
     */
    public function vote(TokenInterface $token, $event, array $attributes)
    {
        if (!($event instanceof ActionEvent)) {
            return VoterInterface::ACCESS_ABSTAIN;
        }

        if (count($attributes) !== 1 || $attributes[0] !== 'RESOURCE_VOTE' || !$this->supportsClass($event->getData()->getEntityName())) {
            return VoterInterface::ACCESS_ABSTAIN;
        }


        return $this->checkPermissions($token, $event);
    }

    /**
     * @param TokenInterface $token
     * @param ActionEvent $event
     * @return int
     * @throws \Symfony\Component\Security\Core\Exception\InvalidArgumentException
     */
    protected function checkPermissions(TokenInterface $token, ActionEvent $event)
    {
        switch ($event->getAction()) {
            case 'post':
                return $this->post($token, $event->getData());
                break;
            case 'put':
                return $this->put($token, $event->getData());
                break;
            case 'patch':
                return $this->patch($token, $event->getData());
                break;
            case 'patch_property':
                return $this->patchProperty($token, $event->getData());
                break;
            case 'get':
                return $this->get($token, $event->getData());
                break;
            case 'get_collection':
                return $this->getCollection($token, $event->getData());
                break;
            case 'get_link_collection':
                return $this->getLinkCollection($token, $event->getData());
                break;
            case 'add_link':
                return $this->addLink($token, $event->getData());
                break;
            case 'remove_link':
                return $this->removeLink($token, $event->getData());
                break;
            case 'remove':
                return $this->remove($token, $event->getData());
            default:
                throw new InvalidArgumentException();
        }

    }


    /**
     * @param TokenInterface $token
     * @param GetActionEventData $data
     * @return int
     */
    protected function get(TokenInterface $token, GetActionEventData $data) {}

    /**
     * @param TokenInterface $token
     * @param GetCollectionActionEventData $data
     * @return int
     */
    protected function getCollection(TokenInterface $token, GetCollectionActionEventData $data) {}

    /**
     * @param TokenInterface $token
     * @param GetLinkCollectionActionEventData $data
     * @return int
     */
    protected function getLinkCollection(TokenInterface $token, GetLinkCollectionActionEventData $data) {}


    /**
     * @param TokenInterface $token
     * @param PostActionEventData $data
     * @return int
     */
    protected function post(TokenInterface $token, PostActionEventData $data) {}

    /**
     * @param TokenInterface $token
     * @param PatchActionEventData $data
     * @return int
     */
    protected function patch(TokenInterface $token, PatchActionEventData $data) {}

    /**
     * @param TokenInterface $token
     * @param PatchPropertyActionEventData $data
     * @return int
     */
    protected function patchProperty(TokenInterface $token, PatchPropertyActionEventData $data) {}

    /**
     * @param TokenInterface $token
     * @param RemoveActionEventData $data
     * @return int
     */
    protected function remove(TokenInterface $token, RemoveActionEventData $data) {}

    /**
     * @param TokenInterface $token
     * @param AddLinkActionEventData $data
     * @return int
     */
    protected function addLink(TokenInterface $token, AddLinkActionEventData $data) {}


    /**
     * @param TokenInterface $token
     * @param RemoveLinkActionEventData $data
     * @return int
     */
    protected function removeLink(TokenInterface $token, RemoveLinkActionEventData $data) {}

    /**
     * @param bool $grant
     * @return int
     */
    protected function grantOrDeny($grant)
    {
        if ($grant === TRUE) {
            return VoterInterface::ACCESS_GRANTED;
        } else {
            return VoterInterface::ACCESS_DENIED;
        }
    }

    /**
     * @param $roleName
     * @param TokenInterface $token
     * @return bool
     */
    protected function hasRole($roleName, TokenInterface $token)
    {
        foreach ($this->roleHierarchy->getReachableRoles($token->getRoles()) as $role) {
            if ($roleName === $role->getRole()) {
                return TRUE;
            }
        }
        return FALSE;
    }
}