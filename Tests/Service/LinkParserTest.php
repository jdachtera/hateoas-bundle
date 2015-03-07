<?php
/**
 * Created by PhpStorm.
 * User: jascha
 * Date: 05.03.15
 * Time: 15:27
 */

namespace uebb\HateoasBundle\Test\Service;


use Symfony\Component\HttpFoundation\Request;
use uebb\HateoasBundle\Service\LinkParser;

class LinkParserTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var LinkParser
     */
    private $linkParser;

    public function __construct() {
        $this->linkParser = new LinkParser();
    }

    public function testParseHeaderLinks()
    {
        $expected = array(
            'tests' => array(
                array('href' => 'http://example.com/tests')
            ),
            'first' => array(
                array('href' => 'http://example.com/tests/1')
            )
        );

        $header = '<http://example.com/tests>; rel="tests", <http://example.com/tests/1>; rel="first"';

        $request = new Request();
        $request->headers->set('Link', $header);

        $this->assertEquals($expected, $this->linkParser->parseLinks($request));
    }

    public function testParseBodyLinks()
    {
        $_links = array(
            'tests' => array('href' => 'http://example.com/tests'),
            'first' => array(
                array('href' => 'http://example.com/tests/1')
            )
        );

        $expected = array(
            'tests' => array(
                array('href' => 'http://example.com/tests')
            ),
            'first' => array(
                array('href' => 'http://example.com/tests/1')
            )
        );

        $request = new Request();
        $request->request->set('_links', $_links);

        $this->assertEquals($expected, $this->linkParser->parseLinks($request));
    }

    public function testInvalidInputData()
    {
        $_invalid = array(
            'tests' => 'http://example.com'
        );

        $request = new Request();
        $request->request->set('_links', $_invalid);

        $this->assertEquals(array(), $this->linkParser->parseLinks($request));
    }

    public function testEmptyInput()
    {
        $this->assertEquals(array(), $this->linkParser->parseLinks(new Request()));
    }
}
