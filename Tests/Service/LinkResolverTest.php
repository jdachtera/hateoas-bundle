<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 07.03.15
 * Time: 14:38
 */

namespace uebb\HateoasBundle\Test\Service;


use Symfony\Component\HttpFoundation\Request;
use uebb\HateoasBundle\Controller\HateoasController;
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

    public function __construct()
    {
        $this->mockResource = $this->getMock('uebb\HateoasBundle\Entity\Resource');

        $this->controller = $this->getMock('uebb\HateoasBundle\Controller\HateoasController');
        $this->controller->expects($this->any())
            ->method('getAction')
            ->willReturn(new ResourceView($this->getMock('Symfony\Component\Routing\RouterInterface'), $this->mockResource));

        $this->controllerResolver = $this->getMock('Symfony\Component\HttpKernel\Controller\ControllerResolverInterface');

        $this->controllerResolver->expects($this->any())
            ->method('getController')
            ->willReturn(array($this->controller, 'getAction'));

        $this->controllerResolver->expects($this->any())
            ->method('getArguments')
            ->willReturn(array('id' => 1, 'request' => new Request()));

        $this->context = new \Symfony\Component\Routing\RequestContext();

        $this->urlMatcher = $this->getMock('Symfony\Component\Routing\Matcher\UrlMatcherInterface');

        $this->urlMatcher->expects($this->any())
            ->method('match')
            ->with('/test/1')
            ->willReturn(array(
                '_controller' => 'Test',
                '_action' => 'getAction',
                '_method' => 'GET',
                'id' => 1
            ));

        $this->urlMatcher->expects($this->any())
            ->method('getContext')
            ->willReturn($this->context);

        $this->httpKernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

    }

    public function testLinkResolve()
    {
        $linkResolver = new LinkResolver($this->controllerResolver, $this->urlMatcher, $this->httpKernel, $this->dispatcher);
        $this->assertEquals($this->mockResource, $linkResolver->resolveResourceLink('http://localhost/test/1'));
    }

    public function testMultipleLinkResolve()
    {
        $linkResolver = new LinkResolver($this->controllerResolver, $this->urlMatcher, $this->httpKernel, $this->dispatcher);
        $this->assertEquals(
            array(
                'test' => array(
                    $this->mockResource
                )
            ),
            $linkResolver->resolveResourceLinks(
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
        $linkResolver = new LinkResolver($this->controllerResolver, $this->urlMatcher, $this->httpKernel, $this->dispatcher);
        $linkResolver->resolveResourceLink('http://external.com/test/1');
    }

    /**
     * @expectedException \uebb\HateoasBundle\Exception\InvalidLinkException
     */
    public function testNonExisting()
    {
        $linkResolver = new LinkResolver($this->controllerResolver, $this->urlMatcher, $this->httpKernel, $this->dispatcher);
        $this->urlMatcher->expects($this->any())
            ->method('match')
            ->with('/unknown/1')
            ->willThrowException(new \Symfony\Component\Routing\Exception\ResourceNotFoundException());

        $linkResolver->resolveResourceLink('http://localhost/unknown/1');
    }

    public function testControllerReturningView()
    {


        $linkResolver = new LinkResolver($this->controllerResolver, $this->urlMatcher, $this->httpKernel, $this->dispatcher);

        $this->assertEquals($this->mockResource, $linkResolver->resolveResourceLink('http://localhost/test/1'));

    }
}
