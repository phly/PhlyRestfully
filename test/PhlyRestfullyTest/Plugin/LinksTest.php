<?php

namespace PhlyRestfullyTest\Plugin;

use PhlyRestfully\Plugin\Links;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Mvc\Controller\Plugin\Url as UrlHelper;
use Zend\Mvc\Router\SimpleRouteStack;
use Zend\Mvc\Router\Http\Segment;
use Zend\Mvc\MvcEvent;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;

class LinksTest extends TestCase
{
    public function setUp()
    {
        $this->router = $router = new SimpleRouteStack();
        $route = new Segment('/resource[/[:id]]');
        $router->addRoute('resource', $route);
        $this->event = $event = new MvcEvent();
        $event->setRouter($router);

        $controller = $this->controller = $this->getMock('PhlyRestfully\ResourceController');
        $controller->expects($this->any())
            ->method('getEvent')
            ->will($this->returnValue($event));

        $this->urlHelper = $urlHelper = new UrlHelper();
        $urlHelper->setController($controller);

        $this->serverUrlHelper = $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $this->plugin = $plugin = new Links();
        $plugin->setController($controller);
        $plugin->setUrlHelper($urlHelper);
        $plugin->setServerUrlHelper($serverUrlHelper);
    }

    public function testLinkCreationWithoutIdCreatesFullyQualifiedLink()
    {
        $url = $this->plugin->createLink('resource');
        $this->assertEquals('http://localhost.localdomain/resource', $url);
    }

    public function testLinkCreationWithIdCreatesFullyQualifiedLink()
    {
        $url = $this->plugin->createLink('resource', 123);
        $this->assertEquals('http://localhost.localdomain/resource/123', $url);
    }

    public function testCanGenerateHalLinkRelationsFromSimpleAssociativeArray()
    {
        $input = array(
            'self' => 'self',
            'up'   => 'up',
            'prev' => 'prev',
            'next' => 'next',
        );
        $expected = array(
            'self' => array('href' => 'self'),
            'up'   => array('href' => 'up'),
            'prev' => array('href' => 'prev'),
            'next' => array('href' => 'next'),
        );
        $links = $this->plugin->generateHalLinkRelations($input);
        $this->assertEquals($expected, $links);
    }
}
