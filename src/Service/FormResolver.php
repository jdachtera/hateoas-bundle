<?php
/**
 * Created by PhpStorm.
 * User: uberblick
 * Date: 17.02.15
 * Time: 12:36
 */

namespace uebb\HateoasBundle\Service;


use Doctrine\Common\Annotations\Reader;
use Symfony\Component\Form\FormFactoryInterface;
use uebb\HateoasBundle\Entity\ResourceInterface;

class FormResolver implements FormResolverInterface
{

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var Reader
     */
    protected $annotationReader;

    public function __construct(FormFactoryInterface $formFactory, Reader $annotationReader)
    {
        $this->formFactory = $formFactory;
        $this->annotationReader = $annotationReader;
    }

    public function getForm(ResourceInterface $resource)
    {
        $resourceClassName = \Doctrine\Common\Util\ClassUtils::getClass($resource);

        $resourceParts = explode("\\", $resourceClassName);
        $resourceParts[count($resourceParts) - 2] = 'Form';
        $resourceParts[count($resourceParts) - 1] .= 'Type';

        $formType = implode("\\", $resourceParts);

        if (class_exists($formType)) {
            return $this->formFactory->create(new $formType(), $resource);
        }

        $options = array(
            'data_class' => $resourceClassName
        );

        $builder = $this->formFactory->createBuilder('form', $resource, $options);

        $reflectionClass = new \ReflectionClass($resourceClassName);

        $annotationClass = 'uebb\HateoasBundle\Annotation\FormField';

        foreach ($reflectionClass->getProperties() as $propertyReflection) {
            /**
             * @var \uebb\HateoasBundle\Annotation\FormField $annotation
             */
            $annotation = $this->annotationReader->getPropertyAnnotation($propertyReflection, $annotationClass);
            if ($annotation) {
                $builder->add(
                    $propertyReflection->getName(),
                    $annotation->type,
                    is_array($annotation->options) ? $annotation->options : array()
                );
            }
        }

        $form = $builder->getForm();

        return $form;
    }
}