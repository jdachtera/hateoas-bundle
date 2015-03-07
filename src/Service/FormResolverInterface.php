<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 17.02.15
 * Time: 12:36
 */

namespace uebb\HateoasBundle\Service;


use uebb\HateoasBundle\Entity\ResourceInterface;

interface FormResolverInterface
{

    public function getForm(ResourceInterface $resource);
}