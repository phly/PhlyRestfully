<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\Exception;
use PhlyRestfully\Link;
use PHPUnit\Framework\TestCase as TestCase;

class LinkTest extends TestCase
{
    public function testConstructorTakesLinkRelationName(): void
    {
        $link = new Link('describedby');
        $this->assertEquals('describedby', $link->getRelation());
    }

    public function testCanSetLinkUrl(): void
    {
        $url  = 'http://example.com/docs.html';
        $link = new Link('describedby');
        $link->setUrl($url);
        $this->assertEquals($url, $link->getUrl());
    }

    public function testCanSetLinkRoute(): void
    {
        $route = 'api/docs';
        $link = new Link('describedby');
        $link->setRoute($route);
        $this->assertEquals($route, $link->getRoute());
    }

    public function testCanSetRouteParamsWhenSpecifyingRoute(): void
    {
        $route  = 'api/docs';
        $params = ['version' => '1.1'];
        $link = new Link('describedby');
        $link->setRoute($route, $params);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($params, $link->getRouteParams());
    }

    public function testCanSetRouteOptionsWhenSpecifyingRoute(): void
    {
        $route   = 'api/docs';
        $options = ['query' => 'version=1.1'];
        $link = new Link('describedby');
        $link->setRoute($route, null, $options);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($options, $link->getRouteOptions());
    }

    public function testCanSetRouteParamsSeparately(): void
    {
        $route  = 'api/docs';
        $params = ['version' => '1.1'];
        $link = new Link('describedby');
        $link->setRoute($route);
        $link->setRouteParams($params);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($params, $link->getRouteParams());
    }

    public function testCanSetRouteOptionsSeparately(): void
    {
        $route   = 'api/docs';
        $options = ['query' => 'version=1.1'];
        $link = new Link('describedby');
        $link->setRoute($route);
        $link->setRouteOptions($options);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($options, $link->getRouteOptions());
    }

    public function testSettingUrlAfterSettingRouteRaisesException(): void
    {
        $link = new Link('describedby');
        $link->setRoute('api/docs');

        $this->expectException(Exception\DomainException::class);
        $link->setUrl('http://example.com/api/docs.html');
    }

    public function testSettingRouteAfterSettingUrlRaisesException(): void
    {
        $link = new Link('describedby');
        $link->setUrl('http://example.com/api/docs.html');

        $this->expectException(Exception\DomainException::class);
        $link->setRoute('api/docs');
    }

    public function testIsCompleteReturnsFalseIfNeitherUrlNorRouteIsSet(): void
    {
        $link = new Link('describedby');
        $this->assertFalse($link->isComplete());
    }

    public function testHasUrlReturnsFalseWhenUrlIsNotSet(): void
    {
        $link = new Link('describedby');
        $this->assertFalse($link->hasUrl());
    }

    public function testHasUrlReturnsTrueWhenUrlIsSet(): void
    {
        $link = new Link('describedby');
        $link->setUrl('http://example.com/api/docs.html');
        $this->assertTrue($link->hasUrl());
    }

    public function testIsCompleteReturnsTrueWhenUrlIsSet(): void
    {
        $link = new Link('describedby');
        $link->setUrl('http://example.com/api/docs.html');
        $this->assertTrue($link->isComplete());
    }

    public function testHasRouteReturnsFalseWhenRouteIsNotSet(): void
    {
        $link = new Link('describedby');
        $this->assertFalse($link->hasRoute());
    }

    public function testHasRouteReturnsTrueWhenRouteIsSet(): void
    {
        $link = new Link('describedby');
        $link->setRoute('api/docs');
        $this->assertTrue($link->hasRoute());
    }

    public function testIsCompleteReturnsTrueWhenRouteIsSet(): void
    {
        $link = new Link('describedby');
        $link->setRoute('api/docs');
        $this->assertTrue($link->isComplete());
    }

    /**
     * @group 79
     */
    public function testFactoryCanGenerateLinkWithUrl(): void
    {
        $rel  = 'describedby';
        $url  = 'http://example.com/docs.html';
        $link = Link::factory([
            'rel' => $rel,
            'url' => $url,
        ]);
        $this->assertInstanceOf(Link::class, $link);
        $this->assertEquals($rel, $link->getRelation());
        $this->assertEquals($url, $link->getUrl());
    }

    /**
     * @group 79
     */
    public function testFactoryCanGenerateLinkWithRouteInformation(): void
    {
        $rel     = 'describedby';
        $route   = 'api/docs';
        $params  = ['version' => '1.1'];
        $options = ['query' => 'version=1.1'];
        $link = Link::factory([
            'rel'   => $rel,
            'route' => [
                'name'    => $route,
                'params'  => $params,
                'options' => $options,
            ],
        ]);

        $this->assertInstanceOf(Link::class, $link);
        $this->assertEquals('describedby', $link->getRelation());
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($params, $link->getRouteParams());
        $this->assertEquals($options, $link->getRouteOptions());
    }
}
