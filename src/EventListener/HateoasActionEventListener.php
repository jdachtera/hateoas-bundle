<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 30.01.15
 * Time: 08:32
 */

namespace uebb\HateoasBundle\EventListener;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use uebb\HateoasBundle\Event\HateoasActionEvent;


class HateoasActionEventListener
{
    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;


    /**
     * Check the permission's of a crud action
     *
     * @param HateoasActionEvent $event
     * @throws AccessDeniedHttpException
     */
    public function checkPermission(HateoasActionEvent $event)
    {

        // check for edit access
        if (false === $this->authorizationChecker->isGranted(array('ENTITY_VOTE'), $event)) {
            $message = '';

            switch ($event->getMethod()) {
                case 'cget':
                    $message = 'You are not allowed to list all ' . $event->getEntitiyName() . ' resources';
                    break;
                case 'get':
                    $message = 'You are not allowed to get the resource ' . $event->getEntitiyName() . ':' . ($event->getresource()->getId() ? $event->getresource()->getId() : 'new');
                    break;
                case 'post':
                    $message = 'You are not allowed to post a new ' . $event->getEntitiyName() . ' resource';
                    break;
                case 'patchProperty':
                    $message = 'You are not allowed to change the property ' . $event->getPropertyName() . ' of the resource ' . $event->getEntitiyName() . ' ' . ($event->getresource()->getId() ? $event->getresource()->getId() : 'new') . ' to the value "' . $event->getPropertyValue() . '"';
                    break;
                case 'patch':
                    $message = 'You are not allowed to patch the resource ' . $event->getEntitiyName() . ':' . ($event->getresource()->getId() ? $event->getresource()->getId() : 'new');
                    break;
                case 'put':
                    $message = 'You are not allowed to put the resource ' . $event->getEntitiyName() . ':' . ($event->getresource()->getId() ? $event->getresource()->getId() : 'new');
                    break;
                case 'lget':
                    $message = 'You are not allowed to get the related ' . $event->getPropertyName() . ' of the resource ' . $event->getEntitiyName() . ':' . ($event->getresource()->getId() ? $event->getresource()->getId() : 'new');
                    break;
                case 'link':
                    $message = 'You are not allowed to link the resource ' . $event->getEntitiyName() . ':' . ($event->getresource()->getId() ? $event->getresource()->getId() : 'new') . ' with the resource ' . $this->getEntityName($event->getPropertyValue()) . ':' . $event->getPropertyValue()->getId() . ' with rel "' . $event->getPropertyName() . '"';
                    break;
                case 'unlink':
                    $message = 'You are not allowed to unlink the resource ' . $event->getEntitiyName() . ':' . ($event->getresource()->getId() ? $event->getresource()->getId() : 'new') . ' from the resource ' . $this->getEntityName($event->getPropertyValue()) . ':' . $event->getPropertyValue()->getId() . ' with rel "' . $event->getPropertyName() . '"';
                    break;
                case 'delete':
                    $message = 'You are not allowed to delete the resource ' . $event->getEntitiyName() . ':' . ($event->getresource()->getId() ? $event->getresource()->getId() : 'new');
                    break;

            }

            throw new AccessDeniedHttpException("Access not allowed. " . $message, NULL, 403);
        }
    }
}