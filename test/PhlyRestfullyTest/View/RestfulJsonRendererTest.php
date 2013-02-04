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
use Zend\Mvc\Router\Http\Segment;
use Zend\Mvc\Router\SimpleRouteStack;
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
        $this->assertEquals('http://localhost.localdomain/resource/identifier', $links->self);
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
        $this->assertEquals('http://localhost.localdomain/resource/identifier', $links->self);
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
        $this->assertEquals('http://localhost.localdomain/resource/identifier', $links->self);
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
        $this->assertEquals('http://localhost.localdomain/resource/identifier', $links->self);
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
        $this->assertEquals('http://localhost.localdomain/resource', $links->self);

        $this->assertObjectHasAttribute('collection', $test);
        $this->assertInternalType('array', $test->collection);
        $this->assertEquals(100, count($test->collection));

        foreach ($test->collection as $key => $item) {
            $id = $key + 1;

            $this->assertObjectHasAttribute('_links', $item);
            $links = $item->_links;
            $this->assertInstanceof('stdClass', $links, var_export($links, 1));
            $this->assertObjectHasAttribute('self', $links);
            $this->assertEquals('http://localhost.localdomain/resource/' . $id, $links->self);
            $this->assertObjectHasAttribute('id', $item, var_export($item, 1));
            $this->assertEquals($id, $item->id);
            $this->assertObjectHasAttribute('foo', $item);
            $this->assertEquals('bar', $item->foo);
        }
    }
}
