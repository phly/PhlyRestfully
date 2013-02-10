<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\Exception;
use PhlyRestfully\Plugin;
use PhlyRestfully\Resource;
use PhlyRestfully\ResourceController;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;
use stdClass;
use Zend\EventManager\EventManager;
use Zend\EventManager\SharedEventManager;
use Zend\Mvc\Controller\PluginManager;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\Http\Segment;
use Zend\Mvc\Router\RouteMatch;
use Zend\Mvc\Router\SimpleRouteStack;
use Zend\Paginator\Adapter\ArrayAdapter as ArrayPaginator;
use Zend\Paginator\Paginator;
use Zend\Stdlib\Parameters;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;
use Zend\View\Helper\Url as UrlHelper;

/**
 * @subpackage UnitTest
 */
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
        $event->setRouteMatch(new RouteMatch(array()));
        $controller->setEvent($event);
        $controller->setRoute('resource');

        $pluginManager = new PluginManager();
        $controller->setPluginManager($pluginManager);

        $urlHelper = new UrlHelper();
        $urlHelper->setRouter($this->router);

        $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $linksHelper = new Plugin\HalLinks();
        $linksHelper->setUrlHelper($urlHelper);
        $linksHelper->setServerUrlHelper($serverUrlHelper);

        $pluginManager->setService('HalLinks', $linksHelper);
        $linksHelper->setController($controller);

        $this->resource = $resource = new Resource();
        $controller->setResource($resource);
    }

    public function assertProblemApiResult($expectedHttpStatus, $expectedDetail, $result)
    {
        $this->assertInstanceOf('PhlyRestfully\ApiProblem', $result);
        $problem = $result->toArray();
        $this->assertEquals($expectedHttpStatus, $problem['httpStatus']);
        $this->assertContains($expectedDetail, $problem['detail']);
    }

    public function testCreateReturnsProblemResultOnCreationException()
    {
        $this->resource->getEventManager()->attach('create', function ($e) {
            throw new Exception\CreationException('failed');
        });

        $result = $this->controller->create(array());
        $this->assertProblemApiResult(500, 'failed', $result);
    }

    public function testCreateReturnsProblemResultOnBadItemIdentifier()
    {
        $this->resource->getEventManager()->attach('create', function ($e) {
            return array('foo' => 'bar');
        });

        $result = $this->controller->create(array());
        $this->assertProblemApiResult(422, 'item identifier', $result);
    }

    public function testCreateReturnsHalItemOnSuccess()
    {
        $item = array('id' => 'foo', 'bar' => 'baz');
        $this->resource->getEventManager()->attach('create', function ($e) use ($item) {
            return $item;
        });

        $result = $this->controller->create(array());
        $this->assertInstanceOf('PhlyRestfully\HalItem', $result);
        $this->assertEquals($item, $result->item);
    }

    public function testFalseFromDeleteResourceReturnsProblemApiResult()
    {
        $this->resource->getEventManager()->attach('delete', function ($e) {
            return false;
        });

        $result = $this->controller->delete('foo');
        $this->assertProblemApiResult(422, 'delete', $result);
    }

    public function testTrueFromDeleteResourceReturnsResponseWithNoContent()
    {
        $this->resource->getEventManager()->attach('delete', function ($e) {
            return true;
        });

        $result = $this->controller->delete('foo');
        $this->assertInstanceOf('Zend\Http\Response', $result);
        $this->assertEquals(204, $result->getStatusCode());
    }

    public function testReturningEmptyResultFromGetReturnsProblemApiResult()
    {
        $this->resource->getEventManager()->attach('fetch', function ($e) {
            return false;
        });

        $result = $this->controller->get('foo');
        $this->assertProblemApiResult(404, 'not found', $result);
    }

    public function testReturningItemFromGetReturnsExpectedHalItem()
    {
        $item = array('id' => 'foo', 'bar' => 'baz');
        $this->resource->getEventManager()->attach('fetch', function ($e) use ($item) {
            return $item;
        });

        $result = $this->controller->get('foo');
        $this->assertInstanceOf('PhlyRestfully\HalItem', $result);
        $this->assertEquals($item, $result->item);
    }

    public function testReturnsHalCollectionForNonPaginatedList()
    {
        $items = array(
            array('id' => 'foo', 'bar' => 'baz')
        );
        $this->resource->getEventManager()->attach('fetchAll', function ($e) use ($items) {
            return $items;
        });

        $result = $this->controller->getList();
        $this->assertInstanceOf('PhlyRestfully\HalCollection', $result);
        $this->assertEquals($items, $result->collection);
    }

    public function testReturnsHalCollectionForPaginatedList()
    {
        $items = array(
            array('id' => 'foo', 'bar' => 'baz'),
            array('id' => 'bar', 'bar' => 'baz'),
            array('id' => 'baz', 'bar' => 'baz'),
        );
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);
        $this->resource->getEventManager()->attach('fetchAll', function ($e) use ($paginator) {
            return $paginator;
        });

        $this->controller->setPageSize(1);
        $request = $this->controller->getRequest();
        $request->setQuery(new Parameters(array('page' => 2)));

        $result = $this->controller->getList();
        $this->assertInstanceOf('PhlyRestfully\HalCollection', $result);
        $this->assertSame($paginator, $result->collection);
        $this->assertEquals(2, $result->page);
        $this->assertEquals(1, $result->pageSize);
    }

    public function testHeadReturnsListResponseWhenNoIdProvided()
    {
        $items = array(
            array('id' => 'foo', 'bar' => 'baz'),
            array('id' => 'bar', 'bar' => 'baz'),
            array('id' => 'baz', 'bar' => 'baz'),
        );
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);
        $this->resource->getEventManager()->attach('fetchAll', function ($e) use ($paginator) {
            return $paginator;
        });

        $this->controller->setPageSize(1);
        $request = $this->controller->getRequest();
        $request->setQuery(new Parameters(array('page' => 2)));

        $result = $this->controller->head();
        $this->assertInstanceOf('PhlyRestfully\HalCollection', $result);
        $this->assertSame($paginator, $result->collection);
    }

    public function testHeadReturnsItemResponseWhenIdProvided()
    {
        $item = array('id' => 'foo', 'bar' => 'baz');
        $this->resource->getEventManager()->attach('fetch', function ($e) use ($item) {
            return $item;
        });

        $result = $this->controller->head('foo');
        $this->assertInstanceOf('PhlyRestfully\HalItem', $result);
        $this->assertEquals($item, $result->item);
    }

    public function testOptionsReturnsEmptyResponseWithAllowHeaderPopulatedForResource()
    {
        $r = new ReflectionObject($this->controller);
        $httpOptionsProp = $r->getProperty('resourceHttpOptions');
        $httpOptionsProp->setAccessible(true);
        $httpOptions = $httpOptionsProp->getValue($this->controller);
        sort($httpOptions);

        $result = $this->controller->options();
        $this->assertInstanceOf('Zend\Http\Response', $result);
        $this->assertEquals(204, $result->getStatusCode());
        $headers = $result->getHeaders();
        $this->assertTrue($headers->has('allow'));
        $allow = $headers->get('allow');
        $test  = $allow->getFieldValue();
        $test  = explode(', ', $test);
        sort($test);
        $this->assertEquals($httpOptions, $test);
    }

    public function testOptionsReturnsEmptyResponseWithAllowHeaderPopulatedForItem()
    {
        $r = new ReflectionObject($this->controller);
        $httpOptionsProp = $r->getProperty('itemHttpOptions');
        $httpOptionsProp->setAccessible(true);
        $httpOptions = $httpOptionsProp->getValue($this->controller);
        sort($httpOptions);

        $this->event->getRouteMatch()->setParam('id', 'foo');

        $result = $this->controller->options();
        $this->assertInstanceOf('Zend\Http\Response', $result);
        $this->assertEquals(204, $result->getStatusCode());
        $headers = $result->getHeaders();
        $this->assertTrue($headers->has('allow'));
        $allow = $headers->get('allow');
        $test  = $allow->getFieldValue();
        $test  = explode(', ', $test);
        sort($test);
        $this->assertEquals($httpOptions, $test);
    }


    public function testPatchReturnsProblemResultOnPatchException()
    {
        $this->resource->getEventManager()->attach('patch', function ($e) {
            throw new Exception\PatchException('failed');
        });

        $result = $this->controller->patch('foo', array());
        $this->assertProblemApiResult(500, 'failed', $result);
    }

    public function testPatchReturnsHalItemOnSuccess()
    {
        $item = array('id' => 'foo', 'bar' => 'baz');
        $this->resource->getEventManager()->attach('patch', function ($e) use ($item) {
            return $item;
        });

        $result = $this->controller->patch('foo', $item);
        $this->assertInstanceOf('PhlyRestfully\HalItem', $result);
        $this->assertEquals($item, $result->item);
    }

    public function testUpdateReturnsProblemResultOnUpdateException()
    {
        $this->resource->getEventManager()->attach('update', function ($e) {
            throw new Exception\UpdateException('failed');
        });

        $result = $this->controller->update('foo', array());
        $this->assertProblemApiResult(500, 'failed', $result);
    }

    public function testUpdateReturnsHalItemOnSuccess()
    {
        $item = array('id' => 'foo', 'bar' => 'baz');
        $this->resource->getEventManager()->attach('update', function ($e) use ($item) {
            return $item;
        });

        $result = $this->controller->update('foo', $item);
        $this->assertInstanceOf('PhlyRestfully\HalItem', $result);
        $this->assertEquals($item, $result->item);
    }

    public function testReplaceListReturnsProblemResultOnUpdateException()
    {
        $this->resource->getEventManager()->attach('replaceList', function ($e) {
            throw new Exception\UpdateException('failed');
        });

        $result = $this->controller->replaceList(array());
        $this->assertProblemApiResult(500, 'failed', $result);
    }

    public function testReplaceListReturnsHalCollectionOnSuccess()
    {
        $items = array(
            array('id' => 'foo', 'bar' => 'baz'),
            array('id' => 'bar', 'bar' => 'baz'));
        $this->resource->getEventManager()->attach('replaceList', function ($e) use ($items) {
            return $items;
        });

        $result = $this->controller->replaceList($items);
        $this->assertInstanceOf('PhlyRestfully\HalCollection', $result);
    }

    public function testOnDispatchRaisesDomainExceptionOnMissingResource()
    {
        $controller = new ResourceController();
        $this->setExpectedException('PhlyRestfully\Exception\DomainException', 'ResourceInterface');
        $controller->onDispatch($this->event);
    }

    public function testOnDispatchRaisesDomainExceptionOnMissingRoute()
    {
        $controller = new ResourceController();
        $controller->setResource($this->resource);
        $this->setExpectedException('PhlyRestfully\Exception\DomainException', 'route');
        $controller->onDispatch($this->event);
    }

    public function testOnDispatchReturns405ResponseForInvalidResourceMethod()
    {
        $this->controller->setResourceHttpOptions(array('GET'));
        $request = $this->controller->getRequest();
        $request->setMethod('POST');
        $this->event->setRequest($request);
        $this->event->setResponse($this->controller->getResponse());

        $result = $this->controller->onDispatch($this->event);
        $this->assertInstanceOf('Zend\Http\Response', $result);
        $this->assertEquals(405, $result->getStatusCode());
        $headers = $result->getHeaders();
        $this->assertTrue($headers->has('allow'));
        $allow = $headers->get('allow');
        $this->assertEquals('GET', $allow->getFieldValue());
    }

    public function testOnDispatchReturns405ResponseForInvalidItemMethod()
    {
        $this->controller->setItemHttpOptions(array('GET'));
        $request = $this->controller->getRequest();
        $request->setMethod('PUT');
        $this->event->setRequest($request);
        $this->event->setResponse($this->controller->getResponse());
        $this->event->getRouteMatch()->setParam('id', 'foo');

        $result = $this->controller->onDispatch($this->event);
        $this->assertInstanceOf('Zend\Http\Response', $result);
        $this->assertEquals(405, $result->getStatusCode());
        $headers = $result->getHeaders();
        $this->assertTrue($headers->has('allow'));
        $allow = $headers->get('allow');
        $this->assertEquals('GET', $allow->getFieldValue());
    }

    public function testValidMethodReturningHalOrApiValueIsCastToViewModel()
    {
        $item = array('id' => 'foo', 'bar' => 'baz');
        $this->resource->getEventManager()->attach('fetch', function ($e) use ($item) {
            return $item;
        });

        $this->controller->setItemHttpOptions(array('GET'));

        $request = $this->controller->getRequest();
        $request->setMethod('GET');
        $this->event->setRequest($request);
        $this->event->getRouteMatch()->setParam('id', 'foo');

        $result = $this->controller->onDispatch($this->event);
        $this->assertInstanceof('Zend\View\Model\ModelInterface', $result);
    }

    public function testValidMethodReturningHalOrApiValueCastsReturnToRestfulJsonModelWhenAcceptHeaderIsJson()
    {
        $item = array('id' => 'foo', 'bar' => 'baz');
        $this->resource->getEventManager()->attach('fetch', function ($e) use ($item) {
            return $item;
        });

        $this->controller->setItemHttpOptions(array('GET'));

        $request = $this->controller->getRequest();
        $request->setMethod('GET');
        $request->getHeaders()->addHeaderLine('Accept', 'application/json');
        $this->event->setRequest($request);
        $this->event->getRouteMatch()->setParam('id', 'foo');

        $result = $this->controller->onDispatch($this->event);
        $this->assertInstanceof('PhlyRestfully\View\RestfulJsonModel', $result);
    }

    public function testPassingIdentifierToConstructorAllowsListeningOnThatIdentifier()
    {
        $controller   = new ResourceController('MyNamespace\Controller\Foo');
        $events       = new EventManager();
        $sharedEvents = new SharedEventManager();
        $events->setSharedManager($sharedEvents);
        $controller->setEventManager($events);

        $test = new stdClass;
        $test->flag = false;
        $sharedEvents->attach('MyNamespace\Controller\Foo', 'test', function ($e) use ($test) {
            $test->flag = true;
        });

        $events->trigger('test', $controller, array());
        $this->assertTrue($test->flag);
    }
}
