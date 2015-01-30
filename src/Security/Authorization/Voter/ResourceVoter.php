<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 14.05.14
 * Time: 10:07
 */

namespace uebb\HateoasBundle\Security\Authorization\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use uebb\HateoasBundle\Event\HateoasActionEvent;

/**
 * Class EntityVoter
 *
 * A Base class for HATEOAS resource voters
 *
 * @package uebb\HateoasBundle\Security\Authorization\Voter
 */
abstract class ResourceVoter implements VoterInterface
{

    /**
     * @var array
     */
    protected $supportedEntities = array();

    /**
     * @param string $class
     * @return bool
     */
    public function supportsClass($class)
    {
        foreach ($this->supportedEntities as $entityName) {
            $supportedClass = 'uebb\HateoasBundle\Entity\\' . $entityName;
            if ($supportedClass === $class || is_subclass_of($class, $supportedClass)) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * @param string $attribute
     * @return bool
     */
    public function supportsAttribute($attribute)
    {
        return $attribute === 'ENTITY_VOTE';
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
        if (!($event instanceof HateoasActionEvent)) {
            return VoterInterface::ACCESS_ABSTAIN;
        }
        if (count($attributes) !== 1 || $attributes[0] !== 'ENTITY_VOTE' || !$this->supportsClass($event->getEntity())) {
            return VoterInterface::ACCESS_ABSTAIN;
        }
        $result = $this->checkPermissions($token, $event);

        return $result;
    }

    /**
     * @param TokenInterface $token
     * @param HateoasActionEvent $event
     * @return int
     * @throws \Symfony\Component\Security\Core\Exception\InvalidArgumentException
     */
    protected function checkPermissions(TokenInterface $token, HateoasActionEvent $event)
    {
        switch ($event->getMethod()) {
            case 'post':
                return $this->post($token, $event);
                break;
            case 'put':
                return $this->put($token, $event);
                break;
            case 'patch':
                return $this->patch($token, $event);
                break;
            case 'patchProperty':
                return $this->patchProperty($token, $event);
                break;
            case 'get':
                return $this->get($token, $event);
                break;
            case 'cget':
                return $this->cget($token, $event);
                break;
            case 'lget':
                return $this->lget($token, $event);
                break;
            case 'link':
                return $this->link($token, $event);
                break;
            case 'unlink':
                return $this->unlink($token, $event);
                break;
            case 'delete':
                return $this->delete($token, $event);
            default:
                throw new InvalidArgumentException();
        }
    }

    /**
     * @param TokenInterface $token
     * @param HateoasActionEvent $event
     * @return int
     */
    protected function get(TokenInterface $token, HateoasActionEvent $event)
    {
        return VoterInterface::ACCESS_ABSTAIN;
    }

    /**
     * @param TokenInterface $token
     * @param HateoasActionEvent $event
     * @return int
     */
    protected function cget(TokenInterface $token, HateoasActionEvent $event)
    {
        return $this->get($token, $event);
    }

    /**
     * @param TokenInterface $token
     * @param HateoasActionEvent $event
     * @return int
     */
    protected function lget(TokenInterface $token, HateoasActionEvent $event)
    {
        return $this->get($token, $event);
    }


    /**
     * @param TokenInterface $token
     * @param HateoasActionEvent $event
     * @return int
     */
    protected function post(TokenInterface $token, HateoasActionEvent $event)
    {
        return VoterInterface::ACCESS_ABSTAIN;
    }

    /**
     * @param TokenInterface $token
     * @param HateoasActionEvent $event
     * @return int
     */
    protected function put(TokenInterface $token, HateoasActionEvent $event)
    {
        return $this->post($token, $event);
    }

    /**
     * @param TokenInterface $token
     * @param HateoasActionEvent $event
     * @return int
     */
    protected function patch(TokenInterface $token, HateoasActionEvent $event)
    {
        return $this->post($token, $event);
    }

    /**
     * @param TokenInterface $token
     * @param HateoasActionEvent $event
     * @return int
     */
    protected function patchProperty(TokenInterface $token, HateoasActionEvent $event)
    {
        return $this->patch($token, $event);
    }

    /**
     * @param TokenInterface $token
     * @param HateoasActionEvent $event
     * @return int
     */
    protected function delete(TokenInterface $token, HateoasActionEvent $event)
    {
        return $this->post($token, $event);
    }

    /**
     * @param TokenInterface $token
     * @param HateoasActionEvent $event
     * @return int
     */
    protected function link(TokenInterface $token, HateoasActionEvent $event)
    {
        return $this->post($token, $event);
    }

    /**
     * @param TokenInterface $token
     * @param HateoasActionEvent $event
     * @return int
     */
    protected function unlink(TokenInterface $token, HateoasActionEvent $event)
    {
        return $this->link($token, $event);
    }

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
     * @param User $authUser
     * @param HateoasActionEvent $event
     * @return bool
     */
    protected function isOwnedByUser(User $authUser, HateoasActionEvent $event)
    {
        return $authUser instanceof User && $event->getResource()->getCreatedBy() === $authUser;
    }

    /**
     * @param HateoasActionEvent $event
     * @return bool
     */
    protected function ownerIsChanged(HateoasActionEvent $event)
    {
        return $event->getLinkRelation() === 'createdBy' && $event->getResource()->getCreatedBy() !== NULL;
    }

    /**
     * @param $grantRoles
     * @param $user
     * @return bool
     */
    protected function hasRole($grantRoles, $user)
    {
        foreach ($grantRoles as $role) {
            if (in_array($role, $user->getRoles())) {
                return TRUE;
            }
        }
        return FALSE;
    }
}