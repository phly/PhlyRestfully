<?php

namespace PhlyRestfullyTest;

use PhlyRestfully\Exception;
use PhlyRestfully\Plugin;
use PhlyRestfully\Resource;
use PhlyRestfully\ResourceController;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Mvc\Controller\PluginManager;
use Zend\Mvc\Controller\Plugin\Url as UrlHelper;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\Http\Segment;
use Zend\Mvc\Router\SimpleRouteStack;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;

class ResourceControllerTest extends TestCase
{
    public function setUp()
    {
        $this->controller = $controller = new ResourceController();

        $this->router = $router = new SimpleRouteStack();
        $route = new Segment('/resource[/[:id]]');
        $router->addRoute('resource', $route);
        $this->event = $event = new MvcEvent();
        $event->setRouter($router);
        $controller->setEvent($event);
        $controller->setRoute('resource');

        $pluginManager = new PluginManager();
        $controller->setPluginManager($pluginManager);
        $urlHelper = new UrlHelper();
        $urlHelper->setController($controller);
        $pluginManager->setService('url', $urlHelper);

        $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $linksHelper = new Plugin\Links();
        $linksHelper->setUrlHelper($urlHelper);
        $linksHelper->setServerUrlHelper($serverUrlHelper);
        $pluginManager->setService('links', $linksHelper);
        $linksHelper->setController($controller);

        $apiProblemHelper = new Plugin\ApiProblemResult();
        $pluginManager->setService('apiproblemresult', $apiProblemHelper);
        $apiProblemHelper->setController($controller);

        $this->resource = $resource = new Resource();
        $controller->setResource($resource);
    }

    public function testCreateReturnsProblemResultOnCreationException()
    {
        $this->resource->getEventManager()->attach('create', function ($e) {
            throw new Exception\CreationException('failed');
        });

        $result = $this->controller->create(array());
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('httpStatus', $result);
        $this->assertEquals(500, $result['httpStatus']);
        $this->assertArrayHasKey('detail', $result);
        $this->assertEquals('failed', $result['detail']);
    }

    public function testCreateReturnsProblemResultOnBadItemIdentifier()
    {
        $this->resource->getEventManager()->attach('create', function ($e) {
            return array('foo' => 'bar');
        });

        $result = $this->controller->create(array());
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('httpStatus', $result);
        $this->assertEquals(422, $result['httpStatus']);
        $this->assertArrayHasKey('detail', $result);
        $this->assertContains('item identifier', $result['detail']);
    }

    public function testCreateReturnsHalArrayOnSuccess()
    {
        $item = array('id' => 'foo', 'bar' => 'baz');
        $this->resource->getEventManager()->attach('create', function ($e) use ($item) {
            return $item;
        });

        $result = $this->controller->create(array());
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('_links', $result);
        $this->assertInternalType('array', $result['_links']);
        $this->assertRegexp('#/resource$#', $result['_links']['up']['href']);
        $this->assertRegexp('#/resource/foo$#', $result['_links']['self']['href']);
        $this->assertArrayHasKey('item', $result);
        $this->assertEquals($item, $result['item']);
    }

}
