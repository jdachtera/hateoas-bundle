<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 30.01.15
 * Time: 08:32
 */

namespace uebb\HateoasBundle\EventListener;

use Doctrine\Common\Util\Debug;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use uebb\HateoasBundle\Entity\File;
use uebb\HateoasBundle\Event\ActionEvent;
use uebb\HateoasBundle\Event\HateoasActionEvent;


class FileSaver
{
    /**
     * @var ContainerInterface
     */
    protected $container;


    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Check the permission's of a crud action
     *
     * @param ActionEvent $event
     * @throws AccessDeniedHttpException
     */
    public function onActionEvent(ActionEvent $event)
    {

        $resource = $event->getData()->getResource();

        if ($resource instanceof File) {

            $upload = $resource->getUpload();

            if (!$resource->getName()) {
                $resource->setName($upload->getClientOriginalName());
            }

            $realname = sha1(uniqid(mt_rand(), true)) . '.' . pathinfo($resource->getName(), PATHINFO_EXTENSION);

            $resource->setMimeType($upload->getMimeType());
            $resource->setSize($upload->getMimeType());
            $resource->setRealname($realname);

            $path = $resource->getFullPath($this->container->getParameter('uebb.hateoas.upload_dir'));

            $dir = dirname($path);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $upload->move($dir, $realname);

        }

    }
}