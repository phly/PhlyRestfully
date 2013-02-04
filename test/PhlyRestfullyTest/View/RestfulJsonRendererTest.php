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
use PhlyRestfully\HalItem;
use PhlyRestfully\View\Helper\HalLinks;
use PhlyRestfully\View\RestfulJsonModel;
use PhlyRestfully\View\RestfulJsonRenderer;
use PhlyRestfullyTest\TestAsset;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;
use Zend\Mvc\Router\Http\Segment;
use Zend\Mvc\Router\SimpleRouteStack;
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
        $this->router = $router = new SimpleRouteStack();
        $this->itemRoute = new Segment('/resource[/[:id]]');
        $this->router->addRoute('resource', $this->itemRoute);

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

    public function testRendersHalItemWithAssociatedLinks()
    {
        $this->setUpHelpers();

        $item = new HalItem(array(
            'foo' => 'bar',
            'id'  => 'identifier',
        ), 'identifier', 'resource');
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

    public function testCanRenderStdclassHalItem()
    {
        $this->setUpHelpers();

        $item = (object) array(
            'foo' => 'bar',
            'id'  => 'identifier',
        );

        $item  = new HalItem($item, 'identifier', 'resource');
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

    public function testCanSerializeHydratableHalItem()
    {
        $this->setUpHelpers();
        $this->renderer->addHydrator(
            'PhlyRestfullyTest\TestAsset\ArraySerializable', 
            new Hydrator\ArraySerializable()
        );

        $item  = new TestAsset\ArraySerializable();
        $item  = new HalItem(new TestAsset\ArraySerializable(), 'identifier', 'resource');
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
        $item  = new HalItem(new TestAsset\ArraySerializable(), 'identifier', 'resource');
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

        $collection = new HalCollection($items, 'resource', 'resource');
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

        $this->assertObjectHasAttribute('collection', $test);
        $this->assertInternalType('array', $test->collection);
        $this->assertEquals(100, count($test->collection));

        foreach ($test->collection as $key => $item) {
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

        $collection = new HalCollection($paginator, 'resource', 'resource');
        $collection->setPageSize(5);
        $collection->setPage(3);

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

        $this->assertObjectHasAttribute('collection', $test);
        $this->assertInternalType('array', $test->collection);
        $this->assertEquals(5, count($test->collection));

        foreach ($test->collection as $key => $item) {
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

        $collection = new HalCollection($paginator, 'resource', 'resource');
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
}
