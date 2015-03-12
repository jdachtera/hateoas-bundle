<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 30.01.15
 * Time: 08:32
 */

namespace uebb\HateoasBundle\EventListener;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use uebb\HateoasBundle\Entity\User;
use uebb\HateoasBundle\Event\ActionEvent;
use uebb\HateoasBundle\Event\HateoasActionEvent;


class EncoderInjector
{

    protected $validIds = array(
        'pre_post',
        'pre_patch'
    );

    /**
     * @var EncoderFactory
     */
    protected $encoderFactory;

    /**
     * @param EncoderFactory $encoderFactory
     */
    public function __construct(EncoderFactory $encoderFactory)
    {
        $this->encoderFactory = $encoderFactory;
    }

    /**
     * Injects the password encoder into user resources
     *
     * @param ActionEvent $event
     * @throws AccessDeniedHttpException
     */
    public function onActionEvent(ActionEvent $event)
    {
        if (!in_array($event->getId(), $this->validIds)) {
            return;
        }

        $resource = $event->getData()->getResource();

        if ($resource instanceof User) {
            $resource->setEncoder($this->encoderFactory->getEncoder($resource));
        }

    }
}