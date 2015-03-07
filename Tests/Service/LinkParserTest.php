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

    public function testParseLinks()
    {
        $expected = array(
            'tests' => array(
                array('href' => 'http://example.com/tests')
            ),
            'first' => array(
                array('href' => 'http://example.com/tests/1')
            )
        );

        $_links = array(
            'tests' => array('href' => 'http://example.com/tests'),
            'first' => array(
                array('href' => 'http://example.com/tests/1')
            )
        );

        $header = '<http://example.com/tests>; rel="tests", <http://example.com/tests/1>; rel="first"';

        $request = new Request();
        $request->headers->set('Link', $header);

        $this->assertEquals($expected, $this->linkParser->parseLinks($request));

        $request = new Request();
        $request->request->set('_links', $_links);

        $this->assertEquals($expected, $this->linkParser->parseLinks($request));

    }
}
