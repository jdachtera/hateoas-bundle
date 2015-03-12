<?php
namespace uebb\HateoasBundle\Exception;

class InvalidLinkException extends \Symfony\Component\HttpKernel\Exception\HttpException
{
    public function __construct($href)
    {
        parent::__construct(400, 'The link \''.$href.'\' could not be resolved to a local resource');
    }
}