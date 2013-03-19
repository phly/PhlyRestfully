<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\View;

use PhlyRestfully\ApiProblem;
use PhlyRestfully\HalCollection;
use PhlyRestfully\HalResource;
use PhlyRestfully\Link;
use PhlyRestfully\Plugin\HalLinks;
use PhlyRestfully\View\RestfulJsonModel;
use PhlyRestfully\View\RestfulJsonRenderer;
use PhlyRestfullyTest\TestAsset;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;
use Zend\Mvc\Router\Http\Segment;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\Paginator\Adapter\ArrayAdapter;
use Zend\Paginator\Paginator;
use Zend\Stdlib\Hydrator;
use Zend\View\HelperPluginManager;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

/**
 * @subpackage UnitTest
 */
class RestfulJsonRendererTest extends TestCase
{
    public function setUp()
    {
        $this->renderer = new RestfulJsonRenderer();
    }

    public function nonRestfulJsonModels()
    {
        return array(
            'view-model' => array(new ViewModel(array('foo' => 'bar'))),
            'json-view-model' => array(new JsonModel(array('foo' => 'bar'))),
        );
    }

    /**
     * @dataProvider nonRestfulJsonModels
     */
    public function testPassesNonRestfulJsonModelToParentToRender($model)
    {
        $payload = $this->renderer->render($model);
        $expected = json_encode(array('foo' => 'bar'));
        $this->assertEquals($expected, $payload);
    }

    public function testRendersApiProblemCorrectly()
    {
        $apiProblem = new ApiProblem(401, 'login error', 'http://status.dev/errors.md', 'Unauthorized');
        $model      = new RestfulJsonModel();
        $model->setPayload($apiProblem);
        $test = $this->renderer->render($model);
        $expected = array(
            'httpStatus'  => 401,
            'describedBy' => 'http://status.dev/errors.md',
            'title'       => 'Unauthorized',
            'detail'      => 'login error',
        );
        $this->assertEquals($expected, json_decode($test, true));
    }

    public function setUpHelpers()
    {
        // need to setup routes
        // need to get a url and serverurl helper that have appropriate injections
        $this->router = $router = new TreeRouteStack();
        $this->resourceRoute = new Segment('/resource[/[:id]]');
        $this->router->addRoute('resource', $this->resourceRoute);

        $this->helpers = $helpers  = new HelperPluginManager();
        $serverUrl = $helpers->get('ServerUrl');
        $url       = $helpers->get('url');
        $url->setRouter($router);
        $serverUrl->setScheme('http');
        $serverUrl->setHost('localhost.localdomain');
        $halLinks  = new HalLinks();
        $halLinks->setServerUrlHelper($serverUrl);
        $halLinks->setUrlHelper($url);
        $helpers->setService('HalLinks', $halLinks);

        $this->renderer->setHelperPluginManager($helpers);
    }

    public function testRendersHalResourceWithAssociatedLinks()
    {
        $this->setUpHelpers();

        $item = new HalResource(array(
            'foo' => 'bar',
            'id'  => 'identifier',
        ), 'identifier');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(array('id' => 'identifier'));
        $links->add($self);

        $model = new RestfulJsonModel(array('payload' => $item));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertObjectHasAttribute('_links', $test);
        $links = $test->_links;
        $this->assertInstanceof('stdClass', $links, var_export($test, 1));
        $this->assertObjectHasAttribute('self', $links);
        $this->assertObjectHasAttribute('href', $links->self);
        $this->assertEquals('http://localhost.localdomain/resource/identifier', $links->self->href);
        $this->assertObjectHasAttribute('foo', $test);
        $this->assertEquals('bar', $test->foo);
    }

    public function testCanRenderStdclassHalResource()
    {
        $this->setUpHelpers();

        $item = (object) array(
            'foo' => 'bar',
            'id'  => 'identifier',
        );

        $item  = new HalResource($item, 'identifier', 'resource');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(array('id' => 'identifier'));
        $links->add($self);

        $model = new RestfulJsonModel(array('payload' => $item));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertObjectHasAttribute('_links', $test);
        $links = $test->_links;
        $this->assertInstanceof('stdClass', $links, var_export($links, 1));
        $this->assertObjectHasAttribute('self', $links);
        $this->assertObjectHasAttribute('href', $links->self);
        $this->assertEquals('http://localhost.localdomain/resource/identifier', $links->self->href);
        $this->assertObjectHasAttribute('foo', $test);
        $this->assertEquals('bar', $test->foo);
    }

    public function testCanSerializeHydratableHalResource()
    {
        $this->setUpHelpers();
        $this->renderer->addHydrator(
            'PhlyRestfullyTest\TestAsset\ArraySerializable',
            new Hydrator\ArraySerializable()
        );

        $item  = new TestAsset\ArraySerializable();
        $item  = new HalResource(new TestAsset\ArraySerializable(), 'identifier', 'resource');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(array('id' => 'identifier'));
        $links->add($self);

        $model = new RestfulJsonModel(array('payload' => $item));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertObjectHasAttribute('_links', $test);
        $links = $test->_links;
        $this->assertInstanceof('stdClass', $links, var_export($links, 1));
        $this->assertObjectHasAttribute('self', $links);
        $this->assertObjectHasAttribute('href', $links->self);
        $this->assertEquals('http://localhost.localdomain/resource/identifier', $links->self->href);
        $this->assertObjectHasAttribute('foo', $test);
        $this->assertEquals('bar', $test->foo);
    }

    public function testUsesDefaultHydratorIfAvailable()
    {
        $this->setUpHelpers();
        $this->renderer->setDefaultHydrator(
            new Hydrator\ArraySerializable()
        );

        $item  = new TestAsset\ArraySerializable();
        $item  = new HalResource(new TestAsset\ArraySerializable(), 'identifier', 'resource');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(array('id' => 'identifier'));
        $links->add($self);

        $model = new RestfulJsonModel(array('payload' => $item));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertObjectHasAttribute('_links', $test);
        $links = $test->_links;
        $this->assertInstanceof('stdClass', $links, var_export($links, 1));
        $this->assertObjectHasAttribute('self', $links);
        $this->assertObjectHasAttribute('href', $links->self);
        $this->assertEquals('http://localhost.localdomain/resource/identifier', $links->self->href);
        $this->assertObjectHasAttribute('foo', $test);
        $this->assertEquals('bar', $test->foo);
    }

    public function testCanRenderNonPaginatedHalCollection()
    {
        $this->setUpHelpers();

        $prototype = array('foo' => 'bar');
        $items = array();
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }

        $collection = new HalCollection($items);
        $collection->setCollectionRoute('resource');
        $collection->setResourceRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $model      = new RestfulJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $this->assertObjectHasAttribute('_links', $test);
        $links = $test->_links;
        $this->assertInstanceof('stdClass', $links, var_export($links, 1));
        $this->assertObjectHasAttribute('self', $links);
        $this->assertObjectHasAttribute('href', $links->self);
        $this->assertEquals('http://localhost.localdomain/resource', $links->self->href);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertInstanceof('stdClass', $test->_embedded);
        $this->assertObjectHasAttribute('items', $test->_embedded);
        $this->assertInternalType('array', $test->_embedded->items);
        $this->assertEquals(100, count($test->_embedded->items));

        foreach ($test->_embedded->items as $key => $item) {
            $id = $key + 1;

            $this->assertObjectHasAttribute('_links', $item);
            $links = $item->_links;
            $this->assertInstanceof('stdClass', $links, var_export($links, 1));
            $this->assertObjectHasAttribute('self', $links);
            $this->assertObjectHasAttribute('href', $links->self);
            $this->assertEquals('http://localhost.localdomain/resource/' . $id, $links->self->href);
            $this->assertObjectHasAttribute('id', $item, var_export($item, 1));
            $this->assertEquals($id, $item->id);
            $this->assertObjectHasAttribute('foo', $item);
            $this->assertEquals('bar', $item->foo);
        }
    }

    public function testCanRenderPaginatedHalCollection()
    {
        $this->setUpHelpers();

        $prototype = array('foo' => 'bar');
        $items = array();
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }
        $adapter   = new ArrayAdapter($items);
        $paginator = new Paginator($adapter);

        $collection = new HalCollection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(3);
        $collection->setCollectionRoute('resource');
        $collection->setResourceRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $model      = new RestfulJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $this->assertObjectHasAttribute('_links', $test);
        $links = $test->_links;
        $this->assertInstanceof('stdClass', $links, var_export($links, 1));
        $this->assertObjectHasAttribute('self', $links);
        $this->assertObjectHasAttribute('href', $links->self);
        $this->assertEquals('http://localhost.localdomain/resource?page=3', $links->self->href);
        $this->assertObjectHasAttribute('first', $links);
        $this->assertObjectHasAttribute('href', $links->first);
        $this->assertEquals('http://localhost.localdomain/resource', $links->first->href);
        $this->assertObjectHasAttribute('last', $links);
        $this->assertObjectHasAttribute('href', $links->last);
        $this->assertEquals('http://localhost.localdomain/resource?page=20', $links->last->href);
        $this->assertObjectHasAttribute('prev', $links);
        $this->assertObjectHasAttribute('href', $links->prev);
        $this->assertEquals('http://localhost.localdomain/resource?page=2', $links->prev->href);
        $this->assertObjectHasAttribute('next', $links);
        $this->assertObjectHasAttribute('href', $links->next);
        $this->assertEquals('http://localhost.localdomain/resource?page=4', $links->next->href);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertInstanceof('stdClass', $test->_embedded);
        $this->assertObjectHasAttribute('items', $test->_embedded);
        $this->assertInternalType('array', $test->_embedded->items);
        $this->assertEquals(5, count($test->_embedded->items));

        foreach ($test->_embedded->items as $key => $item) {
            $id = $key + 11;

            $this->assertObjectHasAttribute('_links', $item);
            $links = $item->_links;
            $this->assertInstanceof('stdClass', $links, var_export($links, 1));
            $this->assertObjectHasAttribute('self', $links);
            $this->assertObjectHasAttribute('href', $links->self);
            $this->assertEquals('http://localhost.localdomain/resource/' . $id, $links->self->href);
            $this->assertObjectHasAttribute('id', $item, var_export($item, 1));
            $this->assertEquals($id, $item->id);
            $this->assertObjectHasAttribute('foo', $item);
            $this->assertEquals('bar', $item->foo);
        }
    }

    public function invalidPages()
    {
        return array(
            '-1'   => array(-1),
            '1000' => array(1000),
        );
    }

    /**
     * @dataProvider invalidPages
     */
    public function testRenderingPaginatedCollectionCanReturnApiProblemIfPageIsTooHighOrTooLow($page)
    {
        $this->setUpHelpers();

        $prototype = array('foo' => 'bar');
        $items = array();
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }
        $adapter   = new ArrayAdapter($items);
        $paginator = new Paginator($adapter);

        $collection = new HalCollection($paginator, 'resource');
        $collection->setPageSize(5);

        // Using reflection object so we can force a negative page number if desired
        $r = new ReflectionObject($collection);
        $p = $r->getProperty('page');
        $p->setAccessible(true);
        $p->setValue($collection, $page);

        $model      = new RestfulJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertObjectHasAttribute('httpStatus', $test, var_export($test, 1));
        $this->assertEquals(409, $test->httpStatus);
        $this->assertObjectHasAttribute('detail', $test);
        $this->assertEquals('Invalid page provided', $test->detail);

        $this->assertTrue($this->renderer->isApiProblem());
        $problem = $this->renderer->getApiProblem();
        $this->assertInstanceof('PhlyRestfully\ApiProblem', $problem);
        $problem = $problem->toArray();
        $this->assertEquals(409, $problem['httpStatus']);
    }

    public function testCanHintToApiProblemToRenderStackTrace()
    {
        $exception  = new \Exception('exception message', 500);
        $apiProblem = new ApiProblem(500, $exception);
        $model      = new RestfulJsonModel();
        $model->setPayload($apiProblem);
        $this->renderer->setDisplayExceptions(true);
        $test = $this->renderer->render($model);
        $test = json_decode($test, true);
        $this->assertContains($exception->getMessage() . "\n" . $exception->getTraceAsString(), $test['detail']);
    }

    public function testRendersAttributesAsPartOfNonPaginatedHalCollection()
    {
        $this->setUpHelpers();

        $attributes = array(
            'count' => 100,
            'type'  => 'foo',
        );

        $prototype = array('foo' => 'bar');
        $items = array();
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }

        $collection = new HalCollection($items, 'resource');
        $collection->setAttributes($attributes);

        $model      = new RestfulJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $this->assertObjectHasAttribute('count', $test, var_export($test, 1));
        $this->assertEquals(100, $test->count);
        $this->assertObjectHasAttribute('type', $test);
        $this->assertEquals('foo', $test->type);
    }

    public function testRendersAttributeAsPartOfPaginatedCollectionResource()
    {
        $this->setUpHelpers();

        $attributes = array(
            'count' => 100,
            'type'  => 'foo',
        );

        $prototype = array('foo' => 'bar');
        $items = array();
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }
        $adapter   = new ArrayAdapter($items);
        $paginator = new Paginator($adapter);

        $collection = new HalCollection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(3);
        $collection->setAttributes($attributes);
        $collection->setCollectionRoute('resource');
        $collection->setResourceRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $model      = new RestfulJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $this->assertObjectHasAttribute('count', $test, var_export($test, 1));
        $this->assertEquals(100, $test->count);
        $this->assertObjectHasAttribute('type', $test);
        $this->assertEquals('foo', $test->type);
    }

    public function testCanRenderNestedHalResourcesAsEmbeddedResources()
    {
        $this->setUpHelpers();
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new HalResource(array(
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ), 'matthew', 'user');
        $link = new Link('self');
        $link->setRoute('user')->setRouteParams(array('id' => 'matthew'));
        $child->getLinks()->add($link);

        $item = new HalResource(array(
            'foo'  => 'bar',
            'id'   => 'identifier',
            'user' => $child,
        ), 'identifier', 'resource');
        $link = new Link('self');
        $link->setRoute('resource')->setRouteParams(array('id' => 'identifier'));
        $item->getLinks()->add($link);

        $model = new RestfulJsonModel(array('payload' => $item));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertObjectNotHasAttribute('user', $test);
        $this->assertObjectHasAttribute('_embedded', $test);
        $embedded = $test->_embedded;
        $this->assertObjectHasAttribute('user', $embedded);
        $user = (array) $embedded->user;
        foreach ($child->resource as $key => $value) {
            $this->assertArrayHasKey($key, $user);
            $this->assertEquals($value, $user[$key]);
        }
        $this->assertArrayHasKey('_links', $user);
        $this->assertInstanceof('stdClass', $user['_links']);
        $links = $user['_links'];
        $this->assertObjectHasAttribute('self', $links);
        $this->assertObjectHasAttribute('href', $links->self);
        $this->assertContains('/user/matthew', $links->self->href);
    }

    public function testRendersEmbeddedResourcesOfIndividualNonPaginatedCollectionResources()
    {
        $this->setUpHelpers();
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new HalResource(array(
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ), 'matthew', 'user');
        $link = new Link('self');
        $link->setRoute('user')->setRouteParams(array('id' => 'matthew'));
        $child->getLinks()->add($link);

        $prototype = array('foo' => 'bar', 'user' => $child);
        $items = array();
        foreach (range(1, 3) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }

        $collection = new HalCollection($items);
        $collection->setCollectionRoute('resource');
        $collection->setResourceRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $model = new RestfulJsonModel(array('payload' => $collection));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $collection = $test->_embedded->items;
        foreach ($collection as $item) {
            $this->assertObjectHasAttribute('_embedded', $item);
            $embedded = $item->_embedded;
            $this->assertObjectHasAttribute('user', $embedded);
            $user = (array) $embedded->user;
            foreach ($child->resource as $key => $value) {
                $this->assertArrayHasKey($key, $user);
                $this->assertEquals($value, $user[$key]);
            }
            $this->assertArrayHasKey('_links', $user);
            $this->assertInstanceof('stdClass', $user['_links']);
            $links = $user['_links'];
            $this->assertObjectHasAttribute('self', $links);
            $this->assertObjectHasAttribute('href', $links->self);
            $this->assertContains('/user/matthew', $links->self->href);
        }
    }

    public function testRendersEmbeddedResourcesOfIndividualPaginatedCollectionResources()
    {
        $this->setUpHelpers();
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new HalResource(array(
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ), 'matthew', 'user');
        $link = new Link('self');
        $link->setRoute('user')->setRouteParams(array('id' => 'matthew'));
        $child->getLinks()->add($link);

        $prototype = array('foo' => 'bar', 'user' => $child);
        $items = array();
        foreach (range(1, 3) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }
        $adapter   = new ArrayAdapter($items);
        $paginator = new Paginator($adapter);

        $collection = new HalCollection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(1);
        $collection->setCollectionRoute('resource');
        $collection->setResourceRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $model      = new RestfulJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $collection = $test->_embedded->items;
        foreach ($collection as $item) {
            $this->assertObjectHasAttribute('_embedded', $item, var_export($item, 1));
            $embedded = $item->_embedded;
            $this->assertObjectHasAttribute('user', $embedded);
            $user = (array) $embedded->user;
            foreach ($child->resource as $key => $value) {
                $this->assertArrayHasKey($key, $user);
                $this->assertEquals($value, $user[$key]);
            }
            $this->assertArrayHasKey('_links', $user);
            $this->assertInstanceof('stdClass', $user['_links']);
            $links = $user['_links'];
            $this->assertObjectHasAttribute('self', $links);
            $this->assertObjectHasAttribute('href', $links->self);
            $this->assertContains('/user/matthew', $links->self->href);
        }
    }
}
