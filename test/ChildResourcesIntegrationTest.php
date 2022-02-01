<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\HalCollection;
use PhlyRestfully\HalResource;
use PhlyRestfully\Link;
use PhlyRestfully\Plugin\HalLinks;
use PhlyRestfully\Resource;
use PhlyRestfully\ResourceController;
use PhlyRestfully\View\RestfulJsonModel;
use PhlyRestfully\View\RestfulJsonRenderer;
use PHPUnit\Framework\TestCase as TestCase;
use ReflectionObject;
use Laminas\Http\Request;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\Mvc\Controller\PluginManager as ControllerPluginManager;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Router\RouteMatch;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\HelperPluginManager;
use Laminas\View\Helper\ServerUrl as ServerUrlHelper;
use Laminas\View\Helper\Url as UrlHelper;

/**
 * @subpackage UnitTest
 */
class ChildResourcesIntegrationTest extends TestCase
{
    public function setUp(): void
    {
        $this->setupRouter();
        $this->setupHelpers();
        $this->setupRenderer();
    }

    public function setupHelpers(): void
    {
        if (!$this->router) {
            $this->setupRouter();
        }

        $urlHelper = new UrlHelper();
        $urlHelper->setRouter($this->router);

        $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $this->serviceManager = new ServiceManager();

        $hydratorPluginManager = new HydratorPluginManager($this->serviceManager);
        $this->serviceManager->setService('HydratorManager', $hydratorPluginManager);

        $linksHelper = new HalLinks($hydratorPluginManager);
        $linksHelper->setUrlHelper($urlHelper);
        $linksHelper->setServerUrlHelper($serverUrlHelper);

        $this->helpers = $helpers = new HelperPluginManager($this->serviceManager);
        $helpers->setService('Url', $urlHelper);
        $helpers->setService('ServerUrl', $serverUrlHelper);
        $helpers->setService('HalLinks', $linksHelper);

        $this->plugins = $plugins = new ControllerPluginManager($this->serviceManager);
        $plugins->setService('HalLinks', $linksHelper);
    }

    public function setupRenderer(): void
    {
        if (!$this->helpers) {
            $this->setupHelpers();
        }
        $this->renderer = $renderer = new RestfulJsonRenderer();
        $renderer->setServiceManager($this->serviceManager);
        $renderer->setHelperPluginManager($this->helpers);
    }

    public function setupRouter(): void
    {
        $routes = [
            'parent' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/api/parent[/:parent]',
                    'defaults' => [
                        'controller' => 'Api\ParentController',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'child' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/child[/:child]',
                            'defaults' => [
                                'controller' => 'Api\ChildController',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->router = $router = new TreeRouteStack();
        $router->addRoutes($routes);
    }

    public function setUpParentResource()
    {
        $this->parent = (object) [
            'id'   => 'anakin',
            'name' => 'Anakin Skywalker',
        ];
        $resource = new HalResource($this->parent, 'anakin');

        $link = new Link('self');
        $link->setRoute('parent');
        $link->setRouteParams(['parent'=> 'anakin']);
        $resource->getLinks()->add($link);

        return $resource;
    }

    public function setUpChildResource($id, $name)
    {
        $this->child = (object) [
            'id'   => $id,
            'name' => $name,
        ];
        $resource = new HalResource($this->child, $id);

        $link = new Link('self');
        $link->setRoute('parent/child');
        $link->setRouteParams(['child'=> $id]);
        $resource->getLinks()->add($link);

        return $resource;
    }

    public function setUpChildCollection()
    {
        $children = [
            ['luke', 'Luke Skywalker'],
            ['leia', 'Leia Organa'],
        ];
        $this->collection = [];
        foreach ($children as $info) {
            $collection[] = call_user_func_array([$this, 'setUpChildResource'], $info);
        }
        $collection = new HalCollection($this->collection);
        $collection->setCollectionRoute('parent/child');
        $collection->setResourceRoute('parent/child');
        $collection->setPage(1);
        $collection->setPageSize(10);
        $collection->setCollectionName('child');

        $link = new Link('self');
        $link->setRoute('parent/child');
        $collection->getLinks()->add($link);

        return $collection;
    }

    public function testParentResourceRendersAsExpected(): void
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf(RouteMatch::class, $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertEquals('parent', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('Url')->setRouteMatch($matches);

        $parent = $this->setUpParentResource();
        $model  = new RestfulJsonModel();
        $model->setPayload($parent);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin', $test->_links->self->href);
    }

    public function testChildResourceRendersAsExpected(): void
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin/child/luke';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf(RouteMatch::class, $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertEquals('luke', $matches->getParam('child'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('Url')->setRouteMatch($matches);

        $child = $this->setUpChildResource('luke', 'Luke Skywalker');
        $model = new RestfulJsonModel();
        $model->setPayload($child);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child/luke', $test->_links->self->href);
    }

    public function testChildCollectionRendersAsExpected(): void
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin/child';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf(RouteMatch::class, $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertNull($matches->getParam('child'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('Url')->setRouteMatch($matches);

        $collection = $this->setUpChildCollection();
        $model = new RestfulJsonModel();
        $model->setPayload($collection);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child', $test->_links->self->href);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertObjectHasAttribute('child', $test->_embedded);
        $this->assertIsArray($test->_embedded->child);

        foreach ($test->_embedded->child as $child) {
            $this->assertObjectHasAttribute('_links', $child);
            $this->assertObjectHasAttribute('self', $child->_links);
            $this->assertObjectHasAttribute('href', $child->_links->self);
            $this->assertRegex(
                '#^http://localhost.localdomain/api/parent/anakin/child/[^/]+$#',
                $child->_links->self->href
            );
        }
    }

    public function setUpAlternateRouter(): void
    {
        $routes = [
            'parent' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/api/parent[/:id]',
                    'defaults' => [
                        'controller' => 'Api\ParentController',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'child' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/child[/:child_id]',
                            'defaults' => [
                                'controller' => 'Api\ChildController',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $this->router = $router = new TreeRouteStack();
        $router->addRoutes($routes);
        $this->helpers->get('Url')->setRouter($router);
    }

    public function testChildResourceObjectIdentiferMapping(): void
    {
        $this->setUpAlternateRouter();

        $uri = 'http://localhost.localdomain/api/parent/anakin/child/luke';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf(RouteMatch::class, $matches);
        $this->assertEquals('anakin', $matches->getParam('id'));
        $this->assertEquals('luke', $matches->getParam('child_id'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('Url')->setRouteMatch($matches);

        $child = $this->setUpChildResource('luke', 'Luke Skywalker');
        $model = new RestfulJsonModel();
        $model->setPayload($child);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child/luke', $test->_links->self->href);
    }

    public function testChildResourceIdentifierMappingInsideCollection(): void
    {
        $this->setUpAlternateRouter();

        $uri = 'http://localhost.localdomain/api/parent/anakin/child';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf(RouteMatch::class, $matches);
        $this->assertEquals('anakin', $matches->getParam('id'));
        $this->assertNull($matches->getParam('child_id'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('Url')->setRouteMatch($matches);

        $collection = $this->setUpChildCollection();
        $model = new RestfulJsonModel();
        $model->setPayload($collection);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child', $test->_links->self->href);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertObjectHasAttribute('child', $test->_embedded);
        $this->assertIsArray($test->_embedded->child);

        foreach ($test->_embedded->child as $child) {
            $this->assertObjectHasAttribute('_links', $child);
            $this->assertObjectHasAttribute('self', $child->_links);
            $this->assertObjectHasAttribute('href', $child->_links->self);
            $this->assertRegex(
                '#^http://localhost.localdomain/api/parent/anakin/child/[^/]+$#',
                $child->_links->self->href
            );
        }
    }

    public function testChildResourceObjectIdentiferMappingViaControllerReturn(): void
    {
        $this->setUpAlternateRouter();

        $resource = new Resource();
        $resource->getEventManager()->attach('fetch', function ($e) {
            return (object) [
                'child_id' => 'luke',
                'name'     => 'Luke Skywalker',
            ];
        });
        $controller = new ResourceController();
        $controller->setPluginManager($this->plugins);
        $controller->setResource($resource);
        $controller->setIdentifierName('child_id');
        $r = new ReflectionObject($controller);
        $m = $r->getMethod('getIdentifier');
        $m->setAccessible(true);

        $uri = 'http://localhost.localdomain/api/parent/anakin/child/luke';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf(RouteMatch::class, $matches);
        $this->assertEquals('anakin', $matches->getParam('id'));
        $this->assertEquals('luke', $matches->getParam('child_id'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('Url')->setRouteMatch($matches);

        // Ensure we matched an identifier!
        $id = $m->invoke($controller, $matches, $request);
        $this->assertEquals('luke', $id);

        $result = $controller->get('luke');
        $this->assertInstanceOf(HalResource::class, $result);
        $self = $result->getLinks()->get('self');
        $params = $self->getRouteParams();
        $this->assertArrayHasKey('child_id', $params);
        $this->assertEquals('luke', $params['child_id']);
    }

    public function testChildResourceObjectIdentiferMappingInCollectionsViaControllerReturn(): void
    {
        $this->setUpAlternateRouter();

        $resource = new Resource();
        $resource->getEventManager()->attach('fetchAll', function ($e) {
            return [
                (object) [
                    'child_id' => 'luke',
                    'name'     => 'Luke Skywalker',
                ],
                (object) [
                    'child_id' => 'leia',
                    'name'     => 'Leia Organa',
                ],
            ];
        });
        $controller = new ResourceController();
        $controller->setPluginManager($this->plugins);
        $controller->setResource($resource);
        $controller->setRoute('parent/child');
        $controller->setIdentifierName('child_id');
        $controller->setCollectionName('children');
        $r = new ReflectionObject($controller);
        $m = $r->getMethod('getIdentifier');
        $m->setAccessible(true);

        $uri = 'http://localhost.localdomain/api/parent/anakin/child';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf(RouteMatch::class, $matches);
        $this->assertEquals('anakin', $matches->getParam('id'));
        $this->assertNull($matches->getParam('child_id'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('Url')->setRouteMatch($matches);

        $result = $controller->getList();
        $this->assertInstanceOf(HalCollection::class, $result);

        // Now, what happens if we render this?
        $model = new RestfulJsonModel();
        $model->setPayload($result);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child', $test->_links->self->href);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertObjectHasAttribute('children', $test->_embedded);
        $this->assertIsArray($test->_embedded->children);

        foreach ($test->_embedded->children as $child) {
            $this->assertObjectHasAttribute('_links', $child);
            $this->assertObjectHasAttribute('self', $child->_links);
            $this->assertObjectHasAttribute('href', $child->_links->self);
            $this->assertMatchesRegularExpression(
                '#^http://localhost.localdomain/api/parent/anakin/child/[^/]+$#',
                $child->_links->self->href
            );
        }
    }
}
