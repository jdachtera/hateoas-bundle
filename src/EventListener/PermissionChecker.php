<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 30.01.15
 * Time: 08:32
 */

namespace uebb\HateoasBundle\EventListener;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use uebb\HateoasBundle\Event\ActionEvent;
use uebb\HateoasBundle\Event\HateoasActionEvent;


class PermissionChecker
{
    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

    protected $validIds = array(
        'post_get',
        'post_get_collection',
        'post_get_link_collection',
        'post_post',
        'post_patch',
        'post_patch_property',
        'pre_remove',
        'pre_add_link',
        'pre_remove_link'
    );


    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }


    /**
     * Check the permission's of a crud action
     *
     * @param ActionEvent $event
     * @throws AccessDeniedHttpException
     */
    public function onActionEvent(ActionEvent $event)
    {
        if (!in_array($event->getId(), $this->validIds)) {
            return;
        }

        if (false === $this->authorizationChecker->isGranted(array('RESOURCE_VOTE'), $event)) {
            throw new AccessDeniedHttpException(sprintf('Action %s is not allowed.', $event->getAction()), NULL, 403);
        }

    }
}