<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 07.03.15
 * Time: 14:38
 */

namespace uebb\HateoasBundle\Test\Service;


use Symfony\Component\HttpFoundation\Request;
use uebb\HateoasBundle\Service\LinkResolver;
use uebb\HateoasBundle\View\ResourceView;

class LinkResolverTest extends \PHPUnit_Framework_TestCase
{
    private $controller;

    private $controllerResolver;

    private $context;

    private $urlMatcher;

    private $dispatcher;

    private $httpKernel;

    private $mockResource;

    public function init()
    {
        if (!$this->mockResource) {
            $this->mockResource = $this->getMock('uebb\HateoasBundle\Entity\ResourceInterface');
        }

        $this->controller = $this->getMock('uebb\HateoasBundle\Controller\HateoasController');
        $this->controllerResolver = $this->getMock(
            'Symfony\Component\HttpKernel\Controller\ControllerResolverInterface'
        );
        $this->urlMatcher = $this->getMock('Symfony\Component\Routing\Matcher\UrlMatcherInterface');
        $this->httpKernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->linkResolver = new LinkResolver(
            $this->controllerResolver,
            $this->urlMatcher,
            $this->httpKernel,
            $this->dispatcher
        );

        $this->context = new \Symfony\Component\Routing\RequestContext();

        $this->urlMatcher->expects($this->any())
            ->method('getContext')
            ->willReturn($this->context);

        $this->controller->expects($this->any())
            ->method('getAction')
            ->willReturn($this->mockResource);

        $this->controllerResolver->expects($this->any())
            ->method('getArguments')
            ->willReturn(array('id' => 1, 'request' => new Request()));

        $this->controllerResolver->expects($this->any())
            ->method('getController')
            ->willReturn(array($this->controller, 'getAction'));

        $this->urlMatcher->expects($this->any())
            ->method('match')
            ->willReturnCallback(
                function ($path) {
                    if ($path === '/test/1') {
                        return array(
                            '_controller' => 'Test',
                            '_action' => 'getAction',
                            '_method' => 'GET',
                            'id' => 1
                        );
                    } else {
                        throw new \Symfony\Component\Routing\Exception\ResourceNotFoundException();
                    }
                }
            );
    }

    public function getMockResource()
    {
        return $this->mockResource;
    }

    public function testLinkResolve()
    {
        $this->init();
        $this->assertEquals($this->mockResource, $this->linkResolver->resolveLink('http://localhost/test/1'));
    }

    public function testMultipleLinkResolve()
    {
        $this->init();
        $this->assertEquals(
            array(
                'test' => array(
                    $this->mockResource
                )
            ),
            $this->linkResolver->resolveLinks(
                array(
                    'test' => array(
                        array(
                            'href' => 'http://localhost/test/1'
                        )
                    )
                )
            )
        );
    }

    /**
     * @expectedException \uebb\HateoasBundle\Exception\InvalidLinkException
     */
    public function testExternalUrl()
    {
        $this->init();
        $this->linkResolver->resolveLink('http://external.com/test/1');
    }

    /**
     * @expectedException \uebb\HateoasBundle\Exception\InvalidLinkException
     */
    public function testNonExisting()
    {
        $this->init();
        $this->linkResolver->resolveLink('http://localhost/unknown/1');
    }

    public function testControllerReturningView()
    {
        $mockResource = $this->getMock('uebb\HateoasBundle\Entity\ResourceInterface');
        $this->mockResource = new ResourceView(
            $this->getMock('Symfony\Component\Routing\RouterInterface'),
            $mockResource
        );
        $this->init();
        $this->assertEquals($mockResource, $this->linkResolver->resolveLink('http://localhost/test/1'));
    }
}
