<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 07.03.15
 * Time: 17:27
 */

namespace uebb\HateoasBundle\Test\Service;


use uebb\HateoasBundle\Service\LinkParser;
use uebb\HateoasBundle\Service\RequestProcessor;

class RequestProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RequestProcessor
     */
    protected $requestProcessor;
    protected $entityManager;
    protected $linkParser;
    protected $formResolver;
    protected $dispatcher;
    protected $queryParser;
    protected $serializer;
    protected $validator;

    private function init()
    {

        $this->entityManager = $this->getMock('Doctrine\ORM\EntityManagerInterface');
        $this->linkParser = $this->getMock('uebb\HateoasBundle\Service\LinkParserInterface');
        $this->linkResolver = $this->getMock('uebb\HateoasBundle\Service\LinkResolverInterface');
        $this->formResolver = $this->getMock('uebb\HateoasBundle\Service\FormResolverInterface');
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->queryParser = $this->getMock('uebb\HateoasBundle\Service\QueryParserInterface');;
        $this->serializer = $this->getMock('JMS\Serializer\SerializerInterface');
        $this->validator = $this->getMock('Symfony\Component\Validator\Validator\ValidatorInterface');


        $this->requestProcessor = new RequestProcessor(
            $this->entityManager,
            $this->linkParser,
            $this->linkResolver,
            $this->formResolver,
            $this->dispatcher,
            $this->queryParser,
            $this->serializer,
            $this->validator
        );
    }

    public function testGetPatch()
    {
        $this->init();

        $oldData = array(
            'name' => 'JohnDoe',
            'age' => 31,
            'annualIncome' => 50000,
            'married' => true,
            '_links' => array(
                'wife' => array(
                    array(
                        'href' => 'http://example.com/people/annettedoe'
                    )
                ),
                'mother' => array(
                    array(
                        'href' => 'http://example.com/people/marydoe'
                    )
                )
            )
        );

        $newData = array(
            'name' => 'JohnDoe',
            'age' => 36,
            'numberOfChildren' => 2,
            'married' => false,
            'annualIncome' => 30000,
            '_links' => array(
                'mother' => array(
                    array(
                        'href' => 'http://example.com/people/marydoe'
                    )
                ),
                'child' => array(
                    array(
                        'href' => 'http://example.com/people/juliadoe'
                    ),
                    array(
                        'href' => 'http://example.com/people/maxdoe'
                    )
                )
            )
        );

        $expectedPatch = array(
            array('op' => 'replace', 'path' => '/age', 'value' => 36),
            array('op' => 'replace', 'path' => '/annualIncome', 'value' => 30000),
            array('op' => 'replace', 'path' => '/married', 'value' => false),
            array('op' => 'replace', 'path' => '/numberOfChildren', 'value' => 2),
            array('op' => 'remove', 'path' => '/_links/wife', 'value' => array(
                array(
                    'href' => 'http://example.com/people/annettedoe'
                )
            )),
            array('op' => 'add', 'path' => '/_links/child', 'value' => array(
                array(
                    'href' => 'http://example.com/people/juliadoe'
                ),
                array(
                    'href' => 'http://example.com/people/maxdoe'
                )
            )),
        );

        $patch = $this->requestProcessor->getPatch($oldData, $newData);

        $this->assertEquals(array_multisort($expectedPatch), array_multisort($patch));

    }
}
