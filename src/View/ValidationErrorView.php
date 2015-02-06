<?php
namespace uebb\HateoasBundle\View;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 06.02.15
 * Time: 08:05
 */

class ValidationErrorView extends View{
    /**
     * @param RouterInterface $router
     * @param ConstraintViolationListInterface $validationErrors
     */
    public function __construct(RouterInterface $router, ConstraintViolationListInterface $validationErrors)
    {
        parent::__construct($router);

        $errors = array();


        foreach ($validationErrors as $violation) {
            /** @var ConstraintViolation $violation */
            $violation = $violation;
            if (!isset($errors[$violation->getPropertyPath()])) {
                $errors[$violation->getPropertyPath()] = array();
            }

            $errors[$violation->getPropertyPath()][] = array(
                'message' => $violation->getMessage(),
                'parameters' => $violation->getMessageParameters()
            );

        }

        $this->setData(array(
            'code' => 400,
            'message' => 'Validation Failed',
            'errors' => $errors
        ));

        $this->setStatusCode(400);
    }
}