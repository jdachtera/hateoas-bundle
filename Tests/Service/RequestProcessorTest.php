<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 07.03.15
 * Time: 17:27
 */

namespace uebb\HateoasBundle\Test\Service;


use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use uebb\HateoasBundle\Service\RequestProcessor;


class RequestProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RequestProcessor
     */
    protected $requestProcessor;
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityManager;
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $linkParser;
     /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $formResolver;
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $dispatcher;
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $queryParser;
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $serializer;
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $validator;

    /**
     * Returns an entity manager for testing.
     *
     * @return EntityManager
     */
    protected static function createTestEntityManager()
    {
        if (!class_exists('PDO') || !in_array('sqlite', \PDO::getAvailableDrivers())) {
            \PHPUnit_Framework_TestCase::markTestSkipped('This test requires SQLite support in your environment');
        }

        $config = new \Doctrine\ORM\Configuration();
        $config->setEntityNamespaces(array(
            'UebbHateoasBundle' => 'uebb\\HateoasBundle\\Tests\\Entity'
        ));

        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setProxyNamespace('UebbHateoasTests\Doctrine');


        $reader = new AnnotationReader();

        $metadataDriver = new AnnotationDriver(
            $reader,
            // provide the namespace of the entities you want to tests
            'uebb\\HateoasBundle\\Tests\\Entity'
        );

        $config->setMetadataDriverImpl($metadataDriver);
        $config->setQueryCacheImpl(new \Doctrine\Common\Cache\ArrayCache());
        $config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache());

        $params = array(
            'driver' => 'pdo_sqlite',
            'memory' => true,
        );

        return EntityManager::create($params, $config);
    }

    private function init()
    {
        $this->entityManager = $this->createTestEntityManager();


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

    protected $oldData = array(
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
            'parents' => array(
                array(
                    'href' => 'http://example.com/people/marydoe'
                )
            )
        )
    );

    protected $newData = array(
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

    protected $expectedPatch = array(
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

    public function testGetPatch()
    {
        $this->init();

        $patch = $this->requestProcessor->getPatch($this->oldData, $this->newData);
        $this->assertEquals(array_multisort($this->expectedPatch), array_multisort($patch));
    }

    public function testApplyPatch()
    {
        $this->init();

        $john = new \uebb\HateoasBundle\Tests\Entity\TestPerson();
        $john->setName('JohnDoe');

        $annette = new \uebb\HateoasBundle\Tests\Entity\TestPerson();
        $annette->setName('AnnetteDoe');

        $john->getParents()->add($annette);
        $annette->getChildren()->add($john);

        $peter = new \uebb\HateoasBundle\Tests\Entity\TestPerson();
        $peter->setName('Peter');
        $peter->getEmployees()->add($john);
        $john->setEmployer($peter);

        $patch = array(
            array('op' => 'replace', 'path' => '/name', 'value' => 'John Doe')
        );

        $this->requestProcessor->applyPatch('UebbHateoasBundle:TestPerson', $john, $patch);

        print($john->getName());

        $this->assertEquals('John Doe', $john->getName());
    }
}
