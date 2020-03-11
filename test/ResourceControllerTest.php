<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\ApiProblem;
use PhlyRestfully\Exception;
use PhlyRestfully\HalCollection;
use PhlyRestfully\HalResource;
use PhlyRestfully\Plugin;
use PhlyRestfully\Resource;
use PhlyRestfully\ResourceController;
use PhlyRestfully\View\RestfulJsonModel;
use PHPUnit\Framework\TestCase as TestCase;
use ReflectionObject;
use stdClass;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\SharedEventManager;
use Laminas\Http;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\Mvc\Controller\PluginManager;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Router\Http\Segment;
use Laminas\Mvc\Router\RouteMatch;
use Laminas\Mvc\Router\SimpleRouteStack;
use Laminas\Paginator\Adapter\ArrayAdapter as ArrayPaginator;
use Laminas\Paginator\Paginator;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\Parameters;
use Laminas\View\Helper\ServerUrl as ServerUrlHelper;
use Laminas\View\Helper\Url as UrlHelper;
use Laminas\View\Model\ModelInterface;

/**
 * @subpackage UnitTest
 */
class ResourceControllerTest extends TestCase
{
    public function setUp(): void
    {
        $this->controller = $controller = new ResourceController();

        $this->router = $router = new SimpleRouteStack();
        $route = new Segment('/resource[/[:id]]');
        $router->addRoute('resource', $route);
        $this->event = $event = new MvcEvent();
        $event->setRouter($router);
        $event->setRouteMatch(new RouteMatch([]));
        $controller->setEvent($event);
        $controller->setRoute('resource');

        $serviceManager = new ServiceManager();

        $pluginManager = new PluginManager($serviceManager);
        $controller->setPluginManager($pluginManager);

        $urlHelper = new UrlHelper();
        $urlHelper->setRouter($this->router);

        $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $hydratorPluginManager = new HydratorPluginManager($serviceManager);

        $linksHelper = new Plugin\HalLinks($hydratorPluginManager);
        $linksHelper->setUrlHelper($urlHelper);
        $linksHelper->setServerUrlHelper($serverUrlHelper);

        $pluginManager->setService('HalLinks', $linksHelper);
        $linksHelper->setController($controller);

        $this->resource = $resource = new Resource();
        $controller->setResource($resource);
    }

    public function assertProblemApiResult($expectedHttpStatus, $expectedDetail, $result): void
    {
        $this->assertInstanceOf(ApiProblem::class, $result);
        $problem = $result->toArray();
        $this->assertEquals($expectedHttpStatus, $problem['httpStatus']);
        $this->assertContains($expectedDetail, $problem['detail']);
    }

    public function testCreateReturnsProblemResultOnCreationException(): void
    {
        $this->resource->getEventManager()->attach('create', function ($e): void {
            throw new Exception\CreationException('failed');
        });

        $result = $this->controller->create([]);
        $this->assertProblemApiResult(500, 'failed', $result);
    }

    public function testCreateReturnsProblemResultOnBadResourceIdentifier(): void
    {
        $this->resource->getEventManager()->attach('create', function ($e) {
            return ['foo' => 'bar'];
        });

        $result = $this->controller->create([]);
        $this->assertProblemApiResult(422, 'resource identifier', $result);
    }

    public function testCreateReturnsHalResourceOnSuccess(): void
    {
        $resource = ['id' => 'foo', 'bar' => 'baz'];
        $this->resource->getEventManager()->attach('create', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->create([]);
        $this->assertInstanceOf(HalResource::class, $result);
        $this->assertEquals($resource, $result->resource);
    }

    public function testPatchListReturnsProblemResultOnUpdateException(): void
    {
        $this->resource->getEventManager()->attach('patchList', function ($e): void {
            throw new Exception\UpdateException('failed');
        });

        $result = $this->controller->patchList([]);
        $this->assertProblemApiResult(500, 'failed', $result);
    }

    public function testPatchListReturnsHalCollectionOnSuccess()
    {
        $items = [
            ['id' => 'foo', 'bar' => 'baz'],
            ['id' => 'bar', 'bar' => 'baz']];
        $this->resource->getEventManager()->attach('patchList', function ($e) use ($items) {
            return $items;
        });

        $result = $this->controller->patchList($items);
        $this->assertInstanceOf(HalCollection::class, $result);
        return $result;
    }

    /**
     * @depends testPatchListReturnsHalCollectionOnSuccess
     */
    public function testPatchListReturnsHalCollectionWithRoutesInjected($collection): void
    {
        $this->assertEquals('resource', $collection->collectionRoute);
        $this->assertEquals('resource', $collection->resourceRoute);
    }

    public function testFalseFromDeleteResourceReturnsProblemApiResult(): void
    {
        $this->resource->getEventManager()->attach('delete', function ($e) {
            return false;
        });

        $result = $this->controller->delete('foo');
        $this->assertProblemApiResult(422, 'delete', $result);
    }

    public function testTrueFromDeleteResourceReturnsResponseWithNoContent(): void
    {
        $this->resource->getEventManager()->attach('delete', function ($e) {
            return true;
        });

        $result = $this->controller->delete('foo');
        $this->assertInstanceOf(Http\Response::class, $result);
        $this->assertEquals(204, $result->getStatusCode());
    }

    public function testFalseFromDeleteResourceCollectionReturnsProblemApiResult(): void
    {
        $this->resource->getEventManager()->attach('deleteList', function ($e) {
            return false;
        });

        $result = $this->controller->deleteList([]);
        $this->assertProblemApiResult(422, 'delete collection', $result);
    }

    public function testTrueFromDeleteResourceCollectionReturnsResponseWithNoContent(): void
    {
        $this->resource->getEventManager()->attach('deleteList', function ($e) {
            return true;
        });

        $result = $this->controller->deleteList();
        $this->assertInstanceOf(Http\Response::class, $result);
        $this->assertEquals(204, $result->getStatusCode());
    }

    public function testReturningEmptyResultFromGetReturnsProblemApiResult(): void
    {
        $this->resource->getEventManager()->attach('fetch', function ($e) {
            return false;
        });

        $result = $this->controller->get('foo');
        $this->assertProblemApiResult(404, 'not found', $result);
    }

    public function testReturningResourceFromGetReturnsExpectedHalResource(): void
    {
        $resource = ['id' => 'foo', 'bar' => 'baz'];
        $this->resource->getEventManager()->attach('fetch', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->get('foo');
        $this->assertInstanceOf(HalResource::class, $result);
        $this->assertEquals($resource, $result->resource);
    }

    public function testReturnsHalCollectionForNonPaginatedList()
    {
        $items = [
            ['id' => 'foo', 'bar' => 'baz']
        ];
        $this->resource->getEventManager()->attach('fetchAll', function ($e) use ($items) {
            return $items;
        });

        $result = $this->controller->getList();
        $this->assertInstanceOf(HalCollection::class, $result);
        $this->assertEquals($items, $result->collection);
        return $result;
    }

    public function testReturnsHalCollectionForPaginatedList(): void
    {
        $items = [
            ['id' => 'foo', 'bar' => 'baz'],
            ['id' => 'bar', 'bar' => 'baz'],
            ['id' => 'baz', 'bar' => 'baz'],
        ];
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);
        $this->resource->getEventManager()->attach('fetchAll', function ($e) use ($paginator) {
            return $paginator;
        });

        $this->controller->setPageSize(1);
        $request = $this->controller->getRequest();
        $request->setQuery(new Parameters(['page' => 2]));

        $result = $this->controller->getList();
        $this->assertInstanceOf(HalCollection::class, $result);
        $this->assertSame($paginator, $result->collection);
        $this->assertEquals(2, $result->page);
        $this->assertEquals(1, $result->pageSize);
    }

    public function testReturnsHalCollectionForPaginatedListUsingPassedPageSizeParameter(): void
    {
        $items = [
            ['id' => 'foo', 'bar' => 'baz'],
            ['id' => 'bar', 'bar' => 'baz'],
            ['id' => 'baz', 'bar' => 'baz'],
        ];
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);
        $this->resource->getEventManager()->attach('fetchAll', function ($e) use ($paginator) {
            return $paginator;
        });

        $this->controller->setPageSizeParam('page_size');
        $request = $this->controller->getRequest();
        $request->setQuery(new Parameters([
            'page'      => 2,
            'page_size' => 1,
        ]));

        $result = $this->controller->getList();
        $this->assertInstanceOf(HalCollection::class, $result);
        $this->assertSame($paginator, $result->collection);
        $this->assertEquals(2, $result->page);
        $this->assertEquals(1, $result->pageSize);
    }

    /**
     * @depends testReturnsHalCollectionForNonPaginatedList
     */
    public function testHalCollectionReturnedIncludesRoutes($collection): void
    {
        $this->assertEquals('resource', $collection->collectionRoute);
        $this->assertEquals('resource', $collection->resourceRoute);
    }

    public function testHeadReturnsListResponseWhenNoIdProvided(): void
    {
        $items = [
            ['id' => 'foo', 'bar' => 'baz'],
            ['id' => 'bar', 'bar' => 'baz'],
            ['id' => 'baz', 'bar' => 'baz'],
        ];
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);
        $this->resource->getEventManager()->attach('fetchAll', function ($e) use ($paginator) {
            return $paginator;
        });

        $this->controller->setPageSize(1);
        $request = $this->controller->getRequest();
        $request->setQuery(new Parameters(['page' => 2]));

        $result = $this->controller->head();
        $this->assertInstanceOf(HalCollection::class, $result);
        $this->assertSame($paginator, $result->collection);
    }

    public function testHeadReturnsResourceResponseWhenIdProvided(): void
    {
        $resource = ['id' => 'foo', 'bar' => 'baz'];
        $this->resource->getEventManager()->attach('fetch', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->head('foo');
        $this->assertInstanceOf(HalResource::class, $result);
        $this->assertEquals($resource, $result->resource);
    }

    public function testOptionsReturnsEmptyResponseWithAllowHeaderPopulatedForCollection(): void
    {
        $r = new ReflectionObject($this->controller);
        $httpOptionsProp = $r->getProperty('collectionHttpOptions');
        $httpOptionsProp->setAccessible(true);
        $httpOptions = $httpOptionsProp->getValue($this->controller);
        sort($httpOptions);

        $result = $this->controller->options();
        $this->assertInstanceOf(Http\Response::class, $result);
        $this->assertEquals(204, $result->getStatusCode());
        $headers = $result->getHeaders();
        $this->assertTrue($headers->has('allow'));
        $allow = $headers->get('allow');
        $test  = $allow->getFieldValue();
        $test  = explode(', ', $test);
        sort($test);
        $this->assertEquals($httpOptions, $test);
    }

    public function testOptionsReturnsEmptyResponseWithAllowHeaderPopulatedForResource(): void
    {
        $r = new ReflectionObject($this->controller);
        $httpOptionsProp = $r->getProperty('resourceHttpOptions');
        $httpOptionsProp->setAccessible(true);
        $httpOptions = $httpOptionsProp->getValue($this->controller);
        sort($httpOptions);

        $this->event->getRouteMatch()->setParam('id', 'foo');

        $result = $this->controller->options();
        $this->assertInstanceOf(Http\Response::class, $result);
        $this->assertEquals(204, $result->getStatusCode());
        $headers = $result->getHeaders();
        $this->assertTrue($headers->has('allow'));
        $allow = $headers->get('allow');
        $test  = $allow->getFieldValue();
        $test  = explode(', ', $test);
        sort($test);
        $this->assertEquals($httpOptions, $test);
    }


    public function testPatchReturnsProblemResultOnPatchException(): void
    {
        $this->resource->getEventManager()->attach('patch', function ($e): void {
            throw new Exception\PatchException('failed');
        });

        $result = $this->controller->patch('foo', []);
        $this->assertProblemApiResult(500, 'failed', $result);
    }

    public function testPatchReturnsHalResourceOnSuccess(): void
    {
        $resource = ['id' => 'foo', 'bar' => 'baz'];
        $this->resource->getEventManager()->attach('patch', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->patch('foo', $resource);
        $this->assertInstanceOf(HalResource::class, $result);
        $this->assertEquals($resource, $result->resource);
    }

    public function testUpdateReturnsProblemResultOnUpdateException(): void
    {
        $this->resource->getEventManager()->attach('update', function ($e): void {
            throw new Exception\UpdateException('failed');
        });

        $result = $this->controller->update('foo', []);
        $this->assertProblemApiResult(500, 'failed', $result);
    }

    public function testUpdateReturnsHalResourceOnSuccess(): void
    {
        $resource = ['id' => 'foo', 'bar' => 'baz'];
        $this->resource->getEventManager()->attach('update', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->update('foo', $resource);
        $this->assertInstanceOf(HalResource::class, $result);
        $this->assertEquals($resource, $result->resource);
    }

    public function testReplaceListReturnsProblemResultOnUpdateException(): void
    {
        $this->resource->getEventManager()->attach('replaceList', function ($e): void {
            throw new Exception\UpdateException('failed');
        });

        $result = $this->controller->replaceList([]);
        $this->assertProblemApiResult(500, 'failed', $result);
    }

    public function testReplaceListReturnsHalCollectionOnSuccess()
    {
        $items = [
            ['id' => 'foo', 'bar' => 'baz'],
            ['id' => 'bar', 'bar' => 'baz']];
        $this->resource->getEventManager()->attach('replaceList', function ($e) use ($items) {
            return $items;
        });

        $result = $this->controller->replaceList($items);
        $this->assertInstanceOf(HalCollection::class, $result);
        return $result;
    }

    /**
     * @depends testReplaceListReturnsHalCollectionOnSuccess
     */
    public function testReplaceListReturnsHalCollectionWithRoutesInjected($collection): void
    {
        $this->assertEquals('resource', $collection->collectionRoute);
        $this->assertEquals('resource', $collection->resourceRoute);
    }

    public function testOnDispatchRaisesDomainExceptionOnMissingResource(): void
    {
        $controller = new ResourceController();
        $this->expectException(Exception\DomainException::class);
        $this->expectExceptionMessage('ResourceInterface');
        $controller->onDispatch($this->event);
    }

    public function testOnDispatchRaisesDomainExceptionOnMissingRoute(): void
    {
        $controller = new ResourceController();
        $controller->setResource($this->resource);
        $this->expectException(Exception\DomainException::class);
        $this->expectExceptionMessage('route');
        $controller->onDispatch($this->event);
    }

    public function testOnDispatchReturns405ResponseForInvalidCollectionMethod(): void
    {
        $this->controller->setCollectionHttpOptions(['GET']);
        $request = $this->controller->getRequest();
        $request->setMethod('POST');
        $this->event->setRequest($request);
        $this->event->setResponse($this->controller->getResponse());

        $result = $this->controller->onDispatch($this->event);
        $this->assertInstanceOf(Http\Response::class, $result);
        $this->assertEquals(405, $result->getStatusCode());
        $headers = $result->getHeaders();
        $this->assertTrue($headers->has('allow'));
        $allow = $headers->get('allow');
        $this->assertEquals('GET', $allow->getFieldValue());
    }

    public function testOnDispatchReturns405ResponseForInvalidResourceMethod(): void
    {
        $this->controller->setResourceHttpOptions(['GET']);
        $request = $this->controller->getRequest();
        $request->setMethod('PUT');
        $this->event->setRequest($request);
        $this->event->setResponse($this->controller->getResponse());
        $this->event->getRouteMatch()->setParam('id', 'foo');

        $result = $this->controller->onDispatch($this->event);
        $this->assertInstanceOf(Http\Response::class, $result);
        $this->assertEquals(405, $result->getStatusCode());
        $headers = $result->getHeaders();
        $this->assertTrue($headers->has('allow'));
        $allow = $headers->get('allow');
        $this->assertEquals('GET', $allow->getFieldValue());
    }

    public function testValidMethodReturningHalOrApiValueIsCastToViewModel(): void
    {
        $resource = ['id' => 'foo', 'bar' => 'baz'];
        $this->resource->getEventManager()->attach('fetch', function ($e) use ($resource) {
            return $resource;
        });

        $this->controller->setResourceHttpOptions(['GET']);

        $request = $this->controller->getRequest();
        $request->setMethod('GET');
        $this->event->setRequest($request);
        $this->event->getRouteMatch()->setParam('id', 'foo');

        $result = $this->controller->onDispatch($this->event);
        $this->assertInstanceof(ModelInterface::class, $result);
    }

    public function testValidMethodReturningHalOrApiValueCastsReturnToRestfulJsonModelWhenAcceptHeaderIsJson(): void
    {
        $resource = ['id' => 'foo', 'bar' => 'baz'];
        $this->resource->getEventManager()->attach('fetch', function ($e) use ($resource) {
            return $resource;
        });

        $this->controller->setResourceHttpOptions(['GET']);

        $request = $this->controller->getRequest();
        $request->setMethod('GET');
        $request->getHeaders()->addHeaderLine('Accept', 'application/json');
        $this->event->setRequest($request);
        $this->event->getRouteMatch()->setParam('id', 'foo');

        $result = $this->controller->onDispatch($this->event);
        $this->assertInstanceof(RestfulJsonModel::class, $result);
    }

    public function testPassingIdentifierToConstructorAllowsListeningOnThatIdentifier(): void
    {
        $controller   = new ResourceController('MyNamespace\Controller\Foo');
        $sharedEvents = new SharedEventManager();
        $events       = new EventManager($sharedEvents);

        $controller->setEventManager($events);

        $test = new stdClass;
        $test->flag = false;
        $sharedEvents->attach('MyNamespace\Controller\Foo', 'test', function ($e) use ($test): void {
            $test->flag = true;
        });

        $events->trigger('test', $controller, []);
        $this->assertTrue($test->flag);
    }

    public function testHalCollectionUsesControllerCollectionName(): void
    {
        $items = [
            ['id' => 'foo', 'bar' => 'baz']
        ];
        $this->resource->getEventManager()->attach('fetchAll', function ($e) use ($items) {
            return $items;
        });

        $this->controller->setCollectionName('resources');

        $result = $this->controller->getList();
        $this->assertInstanceOf(HalCollection::class, $result);
        $this->assertEquals('resources', $result->collectionName);
    }

    public function testAllowsInjectingContentTypesForRequestMarshalling(): void
    {
        $types = [
            ResourceController::CONTENT_TYPE_JSON => [
                'application/api-problem+json',
                'text/json',
            ],
        ];
        $controller = new ResourceController();
        $controller->setContentTypes($types);

        $this->assertAttributeEquals($types, 'contentTypes', $controller);
    }

    public function testCreateUsesHalResourceReturnedByResource(): void
    {
        $data     = ['id' => 'foo', 'data' => 'bar'];
        $resource = new HalResource($data, 'foo', 'resource');
        $this->resource->getEventManager()->attach('create', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->create($data);
        $this->assertSame($resource, $result);
    }

    public function testPatchListUsesHalCollectionReturnedByResource(): void
    {
        $collection = new HalCollection([]);
        $this->resource->getEventManager()->attach('patchList', function ($e) use ($collection) {
            return $collection;
        });

        $result = $this->controller->patchList([]);
        $this->assertSame($collection, $result);
    }

    public function testGetUsesHalResourceReturnedByResource(): void
    {
        $data     = ['id' => 'foo', 'data' => 'bar'];
        $resource = new HalResource($data, 'foo', 'resource');
        $this->resource->getEventManager()->attach('fetch', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->get('foo');
        $this->assertSame($resource, $result);
    }

    public function testGetListUsesHalCollectionReturnedByResource(): void
    {
        $collection = new HalCollection([]);
        $this->resource->getEventManager()->attach('fetchAll', function ($e) use ($collection) {
            return $collection;
        });

        $result = $this->controller->getList();
        $this->assertSame($collection, $result);
    }

    public function testPatchUsesHalResourceReturnedByResource(): void
    {
        $data     = ['id' => 'foo', 'data' => 'bar'];
        $resource = new HalResource($data, 'foo', 'resource');
        $this->resource->getEventManager()->attach('patch', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->patch('foo', $data);
        $this->assertSame($resource, $result);
    }

    public function testUpdateUsesHalResourceReturnedByResource(): void
    {
        $data     = ['id' => 'foo', 'data' => 'bar'];
        $resource = new HalResource($data, 'foo', 'resource');
        $this->resource->getEventManager()->attach('update', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->update('foo', $data);
        $this->assertSame($resource, $result);
    }

    public function testReplaceListUsesHalCollectionReturnedByResource(): void
    {
        $collection = new HalCollection([]);
        $this->resource->getEventManager()->attach('replaceList', function ($e) use ($collection) {
            return $collection;
        });

        $result = $this->controller->replaceList([]);
        $this->assertSame($collection, $result);
    }

    public function testCreateTriggersPreAndPostEvents(): void
    {
        $test = (object) [
            'pre'       => false,
            'pre_data'  => false,
            'post'      => false,
            'post_data' => false,
            'resource'  => false,
        ];

        $this->controller->getEventManager()->attach('create.pre', function ($e) use ($test): void {
            $test->pre      = true;
            $test->pre_data = $e->getParam('data');
        });
        $this->controller->getEventManager()->attach('create.post', function ($e) use ($test): void {
            $test->post = true;
            $test->post_data = $e->getParam('data');
            $test->resource = $e->getParam('resource');
        });

        $data     = ['id' => 'foo', 'data' => 'bar'];
        $resource = new HalResource($data, 'foo', 'resource');
        $this->resource->getEventManager()->attach('create', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->create($data);
        $this->assertTrue($test->pre);
        $this->assertEquals($data, $test->pre_data);
        $this->assertTrue($test->post);
        $this->assertEquals($data, $test->post_data);
        $this->assertSame($resource, $test->resource);
    }

    public function testPatchListTriggersPreAndPostEvents(): void
    {
        $test = (object) [
            'pre'        => false,
            'pre_data'   => false,
            'post'       => false,
            'post_data'  => false,
            'collection' => false,
        ];

        $this->controller->getEventManager()->attach('patchList.pre', function ($e) use ($test): void {
            $test->pre      = true;
            $test->pre_data = $e->getParam('data');
        });
        $this->controller->getEventManager()->attach('patchList.post', function ($e) use ($test): void {
            $test->post = true;
            $test->post_data = $e->getParam('data');
            $test->collection = $e->getParam('collection');
        });

        $data       = ['foo' => ['id' => 'bar']];
        $collection = new HalCollection($data);
        $this->resource->getEventManager()->attach('patchList', function ($e) use ($collection) {
            return $collection;
        });

        $result = $this->controller->patchList($data);
        $this->assertTrue($test->pre);
        $this->assertEquals($data, $test->pre_data);
        $this->assertTrue($test->post);
        $this->assertEquals($data, $test->post_data);
        $this->assertSame($collection, $test->collection);
    }

    public function testDeleteTriggersPreAndPostEvents(): void
    {
        $test = (object) [
            'pre'       => false,
            'pre_id'  => false,
            'post'      => false,
            'post_id' => false,
        ];

        $this->controller->getEventManager()->attach('delete.pre', function ($e) use ($test): void {
            $test->pre      = true;
            $test->pre_id = $e->getParam('id');
        });
        $this->controller->getEventManager()->attach('delete.post', function ($e) use ($test): void {
            $test->post = true;
            $test->post_id = $e->getParam('id');
        });

        $this->resource->getEventManager()->attach('delete', function ($e) {
            return true;
        });

        $result = $this->controller->delete('foo');
        $this->assertTrue($test->pre);
        $this->assertEquals('foo', $test->pre_id);
        $this->assertTrue($test->post);
        $this->assertEquals('foo', $test->post_id);
    }

    public function testDeleteListTriggersPreAndPostEvents(): void
    {
        $test = (object) [
            'pre'       => false,
            'post'      => false,
        ];

        $this->controller->getEventManager()->attach('deleteList.pre', function ($e) use ($test): void {
            $test->pre      = true;
        });
        $this->controller->getEventManager()->attach('deleteList.post', function ($e) use ($test): void {
            $test->post = true;
        });

        $this->resource->getEventManager()->attach('deleteList', function ($e) {
            return true;
        });

        $result = $this->controller->deleteList([]);
        $this->assertTrue($test->pre);
        $this->assertTrue($test->post);
    }

    public function testGetTriggersPreAndPostEvents(): void
    {
        $test = (object) [
            'pre'       => false,
            'pre_id'    => false,
            'post'      => false,
            'post_id'   => false,
            'resource'  => false,
        ];

        $this->controller->getEventManager()->attach('get.pre', function ($e) use ($test): void {
            $test->pre    = true;
            $test->pre_id = $e->getParam('id');
        });
        $this->controller->getEventManager()->attach('get.post', function ($e) use ($test): void {
            $test->post = true;
            $test->post_id = $e->getParam('id');
            $test->resource = $e->getParam('resource');
        });

        $data     = ['id' => 'foo', 'data' => 'bar'];
        $resource = new HalResource($data, 'foo', 'resource');
        $this->resource->getEventManager()->attach('fetch', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->get('foo');
        $this->assertTrue($test->pre);
        $this->assertEquals('foo', $test->pre_id);
        $this->assertTrue($test->post);
        $this->assertEquals('foo', $test->post_id);
        $this->assertSame($resource, $test->resource);
    }

    public function testOptionsTriggersPreAndPostEventsForCollection(): void
    {
        $options = ['GET', 'POST'];
        $this->controller->setCollectionHttpOptions($options);

        $test = (object) [
            'pre'          => false,
            'post'         => false,
            'pre_options'  => false,
            'post_options' => false,
        ];

        $this->controller->getEventManager()->attach('options.pre', function ($e) use ($test): void {
            $test->pre = true;
            $test->pre_options = $e->getParam('options');
        });
        $this->controller->getEventManager()->attach('options.post', function ($e) use ($test): void {
            $test->post = true;
            $test->post_options = $e->getParam('options');
        });

        $this->controller->options();
        $this->assertTrue($test->pre);
        $this->assertEquals($options, $test->pre_options);
        $this->assertTrue($test->post);
        $this->assertEquals($options, $test->post_options);
    }

    public function testOptionsTriggersPreAndPostEventsForResource(): void
    {
        $options = ['GET', 'PUT', 'PATCH'];
        $this->controller->setResourceHttpOptions($options);

        $test = (object) [
            'pre'          => false,
            'post'         => false,
            'pre_options'  => false,
            'post_options' => false,
        ];

        $this->controller->getEventManager()->attach('options.pre', function ($e) use ($test): void {
            $test->pre = true;
            $test->pre_options = $e->getParam('options');
        });
        $this->controller->getEventManager()->attach('options.post', function ($e) use ($test): void {
            $test->post = true;
            $test->post_options = $e->getParam('options');
        });

        $this->event->getRouteMatch()->setParam('id', 'foo');

        $this->controller->options();
        $this->assertTrue($test->pre);
        $this->assertEquals($options, $test->pre_options);
        $this->assertTrue($test->post);
        $this->assertEquals($options, $test->post_options);
    }

    public function testGetListTriggersPreAndPostEvents(): void
    {
        $test = (object) [
            'pre'        => false,
            'post'       => false,
            'collection' => false,
        ];

        $this->controller->getEventManager()->attach('getList.pre', function ($e) use ($test): void {
            $test->pre    = true;
        });
        $this->controller->getEventManager()->attach('getList.post', function ($e) use ($test): void {
            $test->post = true;
            $test->collection = $e->getParam('collection');
        });

        $collection = new HalCollection([]);
        $this->resource->getEventManager()->attach('fetchAll', function ($e) use ($collection) {
            return $collection;
        });

        $result = $this->controller->getList();
        $this->assertTrue($test->pre);
        $this->assertTrue($test->post);
        $this->assertSame($collection, $test->collection);
    }

    public function testPatchTriggersPreAndPostEvents(): void
    {
        $test = (object) [
            'pre'       => false,
            'pre_id'    => false,
            'pre_data'  => false,
            'post'      => false,
            'post_id'   => false,
            'post_data' => false,
            'resource'  => false,
        ];

        $this->controller->getEventManager()->attach('patch.pre', function ($e) use ($test): void {
            $test->pre      = true;
            $test->pre_id   = $e->getParam('id');
            $test->pre_data = $e->getParam('data');
        });
        $this->controller->getEventManager()->attach('patch.post', function ($e) use ($test): void {
            $test->post = true;
            $test->post_id   = $e->getParam('id');
            $test->post_data = $e->getParam('data');
            $test->resource  = $e->getParam('resource');
        });

        $data     = ['id' => 'foo', 'data' => 'bar'];
        $resource = new HalResource($data, 'foo', 'resource');
        $this->resource->getEventManager()->attach('patch', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->patch('foo', $data);
        $this->assertTrue($test->pre);
        $this->assertEquals('foo', $test->pre_id);
        $this->assertEquals($data, $test->pre_data);
        $this->assertTrue($test->post);
        $this->assertEquals('foo', $test->post_id);
        $this->assertEquals($data, $test->post_data);
        $this->assertSame($resource, $test->resource);
    }

    public function testUpdateTriggersPreAndPostEvents(): void
    {
        $test = (object) [
            'pre'       => false,
            'pre_id'    => false,
            'pre_data'  => false,
            'post'      => false,
            'post_id'   => false,
            'post_data' => false,
            'resource'  => false,
        ];

        $this->controller->getEventManager()->attach('update.pre', function ($e) use ($test): void {
            $test->pre      = true;
            $test->pre_id   = $e->getParam('id');
            $test->pre_data = $e->getParam('data');
        });
        $this->controller->getEventManager()->attach('update.post', function ($e) use ($test): void {
            $test->post = true;
            $test->post_id   = $e->getParam('id');
            $test->post_data = $e->getParam('data');
            $test->resource  = $e->getParam('resource');
        });

        $data     = ['id' => 'foo', 'data' => 'bar'];
        $resource = new HalResource($data, 'foo', 'resource');
        $this->resource->getEventManager()->attach('update', function ($e) use ($resource) {
            return $resource;
        });

        $result = $this->controller->update('foo', $data);
        $this->assertTrue($test->pre);
        $this->assertEquals('foo', $test->pre_id);
        $this->assertEquals($data, $test->pre_data);
        $this->assertTrue($test->post);
        $this->assertEquals('foo', $test->post_id);
        $this->assertEquals($data, $test->post_data);
        $this->assertSame($resource, $test->resource);
    }

    public function testReplaceListTriggersPreAndPostEvents(): void
    {
        $test = (object) [
            'pre'        => false,
            'pre_data'   => false,
            'post'       => false,
            'post_data'  => false,
            'collection' => false,
        ];

        $this->controller->getEventManager()->attach('replaceList.pre', function ($e) use ($test): void {
            $test->pre      = true;
            $test->pre_data = $e->getParam('data');
        });
        $this->controller->getEventManager()->attach('replaceList.post', function ($e) use ($test): void {
            $test->post = true;
            $test->post_data = $e->getParam('data');
            $test->collection = $e->getParam('collection');
        });

        $data       = ['foo' => ['id' => 'bar']];
        $collection = new HalCollection($data);
        $this->resource->getEventManager()->attach('replaceList', function ($e) use ($collection) {
            return $collection;
        });

        $result = $this->controller->replaceList($data);
        $this->assertTrue($test->pre);
        $this->assertEquals($data, $test->pre_data);
        $this->assertTrue($test->post);
        $this->assertEquals($data, $test->post_data);
        $this->assertSame($collection, $test->collection);
    }

    public function testDispatchReturnsEarlyIfApiProblemReturnedFromListener(): void
    {
        $problem  = new ApiProblem(500, 'got an error');
        $listener = function ($e) use ($problem) {
            $e->setParam('api-problem', $problem);
            return $problem;
        };
        $this->controller->getEventManager()->attach('dispatch', $listener, 100);

        $request = $this->controller->getRequest();
        $request->getHeaders()->addHeaderLine('Accept', 'application/json');

        $result = $this->controller->dispatch($request, $this->controller->getResponse());

        $this->assertInstanceOf(RestfulJsonModel::class, $result);
        $this->assertSame($problem, $result->getPayload());
    }

    /**
     */
    public function testGetResourceThrowsExceptionOnMissingResource(): void
    {
        $this->expectException(\PhlyRestfully\Exception\DomainException::class);

        $controller = new ResourceController();
        $controller->getResource();
    }

    public function testGetResourceReturnsSameInstance(): void
    {
        $this->assertEquals($this->resource, $this->controller->getResource());
    }

    public function eventsProducingApiProblems()
    {
        return [
            'delete' => [
                'delete', 'delete', 'foo',
            ],
            'deleteList' => [
                'deleteList', 'deleteList', null,
            ],
            'get' => [
                'fetch', 'get', 'foo',
            ],
            'getList' => [
                'fetchAll', 'getList', null,
            ],
        ];
    }

    /**
     * @group 36
     * @dataProvider eventsProducingApiProblems
     */
    public function testExceptionDuringDeleteReturnsApiProblem($event, $method, $args): void
    {
        $this->resource->getEventManager()->attach($event, function ($e): void {
            throw new \Exception('failed');
        });

        $result = $this->controller->$method($args);
        $this->assertProblemApiResult(500, 'failed', $result);
    }

    public function testIdentifierNameHasSaneDefault(): void
    {
        $this->assertEquals('id', $this->controller->getIdentifierName());
    }

    public function testCanSetIdentifierName(): void
    {
        $this->controller->setIdentifierName('name');
        $this->assertEquals('name', $this->controller->getIdentifierName());
    }

    public function testUsesConfiguredIdentifierNameToGetIdentifier(): void
    {
        $r = new ReflectionObject($this->controller);
        $getIdentifier = $r->getMethod('getIdentifier');
        $getIdentifier->setAccessible(true);

        $this->controller->setIdentifierName('name');

        $routeMatch = $this->event->getRouteMatch();
        $request    = $this->controller->getRequest();

        $routeMatch->setParam('name', 'foo');
        $result = $getIdentifier->invoke($this->controller, $routeMatch, $request);
        $this->assertEquals('foo', $result);

        $routeMatch->setParam('name', false);
        $request->getQuery()->set('name', 'bar');
        $result = $getIdentifier->invoke($this->controller, $routeMatch, $request);
        $this->assertEquals('bar', $result);
    }

    /**
     * @group 44
     */
    public function testCreateAllowsReturningApiProblemFromResource(): void
    {
        $problem = new ApiProblem(400, 'Validation error', null, null, ['email' => 'Invalid email address provided']);
        $this->resource->getEventManager()->attach('create', function ($e) use ($problem) {
            return $problem;
        });

        $result = $this->controller->create([]);
        $this->assertSame($problem, $result);
    }

    /**
     * @group 44
     */
    public function testPatchListAllowsReturningApiProblemFromResource(): void
    {
        $problem = new ApiProblem(400, 'Validation error', null, null, ['email' => 'Invalid email address provided']);
        $this->resource->getEventManager()->attach('patchList', function ($e) use ($problem) {
            return $problem;
        });

        $result = $this->controller->patchList([]);
        $this->assertSame($problem, $result);
    }

    /**
     * @group 44
     */
    public function testDeleteAllowsReturningApiProblemFromResource(): void
    {
        $problem = new ApiProblem(400, 'Invalid identifier', null, null, ['delete' => 'Invalid identifier provided']);
        $this->resource->getEventManager()->attach('delete', function ($e) use ($problem) {
            return $problem;
        });

        $result = $this->controller->delete('foo');
        $this->assertSame($problem, $result);
    }

    /**
     * @group 44
     */
    public function testDeleteListAllowsReturningApiProblemFromResource(): void
    {
        $problem = new ApiProblem(400, 'Invalid list', null, null, ['delete' => 'Invalid collection']);
        $this->resource->getEventManager()->attach('deleteList', function ($e) use ($problem) {
            return $problem;
        });

        $result = $this->controller->deleteList([]);
        $this->assertSame($problem, $result);
    }

    /**
     * @group 44
     */
    public function testGetAllowsReturningApiProblemFromResource(): void
    {
        $problem = new ApiProblem(400, 'Invalid identifier', null, null, ['get' => 'Invalid identifier provided']);
        $this->resource->getEventManager()->attach('fetch', function ($e) use ($problem) {
            return $problem;
        });

        $result = $this->controller->get('foo');
        $this->assertSame($problem, $result);
    }

    /**
     * @group 44
     */
    public function testGetListAllowsReturningApiProblemFromResource(): void
    {
        $problem = new ApiProblem(400, 'Invalid collection', null, null, ['fetchAll' => 'Invalid collection']);
        $this->resource->getEventManager()->attach('fetchAll', function ($e) use ($problem) {
            return $problem;
        });

        $result = $this->controller->getList();
        $this->assertSame($problem, $result);
    }

    /**
     * @group 44
     */
    public function testPatchAllowsReturningApiProblemFromResource(): void
    {
        $problem = new ApiProblem(400, 'Validation error', null, null, ['email' => 'Invalid email address provided']);
        $this->resource->getEventManager()->attach('patch', function ($e) use ($problem) {
            return $problem;
        });

        $result = $this->controller->patch('foo', []);
        $this->assertSame($problem, $result);
    }

    /**
     * @group 44
     */
    public function testUpdateAllowsReturningApiProblemFromResource(): void
    {
        $problem = new ApiProblem(400, 'Validation error', null, null, ['email' => 'Invalid email address provided']);
        $this->resource->getEventManager()->attach('update', function ($e) use ($problem) {
            return $problem;
        });

        $result = $this->controller->update('foo', []);
        $this->assertSame($problem, $result);
    }

    /**
     * @group 44
     */
    public function testReplaceListAllowsReturningApiProblemFromResource(): void
    {
        $problem = new ApiProblem(400, 'Validation error', null, null, ['email' => 'Invalid email address provided']);
        $this->resource->getEventManager()->attach('replaceList', function ($e) use ($problem) {
            return $problem;
        });

        $result = $this->controller->replaceList([]);
        $this->assertSame($problem, $result);
    }
}
