<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\Link;
use PHPUnit_Framework_TestCase as TestCase;

class LinkTest extends TestCase
{
    public function testConstructorTakesLinkReationName()
    {
        $link = new Link('describedby');
        $this->assertEquals('describedby', $link->getRelation());
    }

    public function testCanSetLinkUrl()
    {
        $url  = 'http://example.com/docs.html';
        $link = new Link('describedby');
        $link->setUrl($url);
        $this->assertEquals($url, $link->getUrl());
    }

    public function testCanSetLinkRoute()
    {
        $route = 'api/docs';
        $link = new Link('describedby');
        $link->setRoute($route);
        $this->assertEquals($route, $link->getRoute());
    }

    public function testCanSetRouteParamsWhenSpecifyingRoute()
    {
        $route  = 'api/docs';
        $params = array('version' => '1.1');
        $link = new Link('describedby');
        $link->setRoute($route, $params);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($params, $link->getRouteParams());
    }

    public function testCanSetRouteOptionsWhenSpecifyingRoute()
    {
        $route   = 'api/docs';
        $options = array('query' => 'version=1.1');
        $link = new Link('describedby');
        $link->setRoute($route, null, $options);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($options, $link->getRouteOptions());
    }

    public function testCanSetRouteParamsSeparately()
    {
        $route  = 'api/docs';
        $params = array('version' => '1.1');
        $link = new Link('describedby');
        $link->setRoute($route);
        $link->setRouteParams($params);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($params, $link->getRouteParams());
    }

    public function testCanSetRouteOptionsSeparately()
    {
        $route   = 'api/docs';
        $options = array('query' => 'version=1.1');
        $link = new Link('describedby');
        $link->setRoute($route);
        $link->setRouteOptions($options);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($options, $link->getRouteOptions());
    }

    public function testSettingUrlAfterSettingRouteRaisesException()
    {
        $link = new Link('describedby');
        $link->setRoute('api/docs');

        $this->setExpectedException('PhlyRestfully\Exception\DomainException');
        $link->setUrl('http://example.com/api/docs.html');
    }

    public function testSettingRouteAfterSettingUrlRaisesException()
    {
        $link = new Link('describedby');
        $link->setUrl('http://example.com/api/docs.html');

        $this->setExpectedException('PhlyRestfully\Exception\DomainException');
        $link->setRoute('api/docs');
    }

    public function testIsCompleteReturnsFalseIfNeitherUrlNorRouteIsSet()
    {
        $link = new Link('describedby');
        $this->assertFalse($link->isComplete());
    }

    public function testHasUrlReturnsFalseWhenUrlIsNotSet()
    {
        $link = new Link('describedby');
        $this->assertFalse($link->hasUrl());
    }

    public function testHasUrlReturnsTrueWhenUrlIsSet()
    {
        $link = new Link('describedby');
        $link->setUrl('http://example.com/api/docs.html');
        $this->assertTrue($link->hasUrl());
    }

    public function testIsCompleteReturnsTrueWhenUrlIsSet()
    {
        $link = new Link('describedby');
        $link->setUrl('http://example.com/api/docs.html');
        $this->assertTrue($link->isComplete());
    }

    public function testHasRouteReturnsFalseWhenRouteIsNotSet()
    {
        $link = new Link('describedby');
        $this->assertFalse($link->hasRoute());
    }

    public function testHasRouteReturnsTrueWhenRouteIsSet()
    {
        $link = new Link('describedby');
        $link->setRoute('api/docs');
        $this->assertTrue($link->hasRoute());
    }

    public function testIsCompleteReturnsTrueWhenRouteIsSet()
    {
        $link = new Link('describedby');
        $link->setRoute('api/docs');
        $this->assertTrue($link->isComplete());
    }
}
