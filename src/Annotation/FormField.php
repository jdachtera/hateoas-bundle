<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 05.02.15
 * Time: 15:50
 */

namespace uebb\HateoasBundle\Annotation;

/**
 * Class FormField
 * @package uebb\HateoasBundle\Annotation
 * @Annotation
 */
class FormField
{
    public $type = NULL;
    public $options = array();
}