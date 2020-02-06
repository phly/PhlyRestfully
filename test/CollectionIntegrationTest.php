<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\Factory;
use PhlyRestfully\Plugin\HalLinks;
use PhlyRestfully\Resource;
use PhlyRestfully\ResourceController;
use PhlyRestfully\View\RestfulJsonModel;
use PhlyRestfully\View\RestfulJsonRenderer;
use PHPUnit\Framework\TestCase as TestCase;
use Zend\EventManager\SharedEventManager;
use Zend\Http\PhpEnvironment\Request;
use Zend\Http\PhpEnvironment\Response;
use Zend\Hydrator\HydratorPluginManager;
use Zend\Mvc\Controller\ControllerManager;
use Zend\Mvc\Controller\PluginManager as ControllerPluginManager;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\Mvc\Router\RouteMatch;
use Zend\Mvc\Service;
use Zend\Paginator\Adapter\ArrayAdapter as ArrayPaginator;
use Zend\Paginator\Paginator;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\Parameters;
use Zend\Uri;
use Zend\View\HelperPluginManager;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;
use Zend\View\Helper\Url as UrlHelper;

/**
 * @subpackage UnitTest
 */
class CollectionIntegrationTest extends TestCase
{
    public function setUp()
    {
        $this->setUpRenderer();
        $this->setUpController();
    }

    public function setUpHelpers()
    {
        $this->setupRouter();

        $urlHelper = new UrlHelper();
        $urlHelper->setRouter($this->router);

        $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $this->serviceManager = $this->getServiceManager();

        $hydratorPluginManager = new HydratorPluginManager($this->serviceManager);
        $this->serviceManager->setService('HydratorManager', $hydratorPluginManager);

        $this->linksHelper = $linksHelper = new HalLinks($hydratorPluginManager);
        $linksHelper->setUrlHelper($urlHelper);
        $linksHelper->setServerUrlHelper($serverUrlHelper);

        $plugins = $this->serviceManager->get('ControllerPluginManager');
        $plugins->setService('HalLinks', $linksHelper);

        $this->helpers = $helpers = new HelperPluginManager($this->serviceManager);
        $helpers->setService('Url', $urlHelper);
        $helpers->setService('ServerUrl', $serverUrlHelper);
        $helpers->setService('HalLinks', $linksHelper);
    }

    public function setUpRenderer()
    {
        $this->setupHelpers();
        $this->renderer = $renderer = new RestfulJsonRenderer();
        $renderer->setServiceManager($this->serviceManager);
        $renderer->setHelperPluginManager($this->helpers);
    }

    public function setUpRouter()
    {
        $this->setUpRequest();

        $routes = [
            'resource' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/api/resource[/:id]',
                    'defaults' => [
                        'controller' => 'Api\ResourceController',
                    ],
                ],
            ],
        ];
        $this->router = $router = new TreeRouteStack();
        $router->addRoutes($routes);

        $matches = $router->match($this->request);
        if (!$matches instanceof RouteMatch) {
            $this->fail('Failed to route!');
        }

        $this->matches = $matches;
    }

    public function setUpCollection()
    {
        $collection = [];
        for ($i = 1; $i <= 10; $i += 1) {
            $collection[] = (object) [
                'id'   => $i,
                'name' => "$i of 10",
            ];
        }

        $collection = new Paginator(new ArrayPaginator($collection));

        return $collection;
    }

    public function setUpListeners()
    {
        $this->listeners = new TestAsset\CollectionIntegrationListener();
        $this->listeners->setCollection($this->setUpCollection());
    }

    public function setUpController()
    {
        $this->setUpRouter();
        $this->setUpListeners();

        $resource = new Resource();
        $events   = $resource->getEventManager();
        $this->listeners->attach($events);

        $controller = $this->controller = new ResourceController('Api\ResourceController');
        $controller->setResource($resource);
        $controller->setIdentifierName('id');
        $controller->setPageSize(3);
        $controller->setRoute('resource');
        $controller->setEvent($this->getEvent());

        $plugins = new ControllerPluginManager($this->serviceManager);
        $plugins->setService('HalLinks', $this->linksHelper);
        $controller->setPluginManager($plugins);
    }

    public function setUpRequest()
    {
        $uri = Uri\UriFactory::factory('http://localhost.localdomain/api/resource?query=foo&page=2');

        $request = $this->request = new Request();
        $request->setQuery(new Parameters([
            'query' => 'foo',
            'page'  => 2,
        ]));
        $request->setUri($uri);
        $headers = $request->getHeaders();
        $headers->addHeaderLine('Accept', 'application/json');
        $headers->addHeaderLine('Content-Type', 'application/json');
    }

    public function setUpResponse()
    {
        $this->response = new Response();
    }

    public function getEvent()
    {
        $this->setUpResponse();
        $event = new MvcEvent();
        $event->setRequest($this->request);
        $event->setResponse($this->response);
        $event->setRouter($this->router);
        $event->setRouteMatch($this->matches);
        return $event;
    }

    public function getServiceManager()
    {
        $services = new ServiceManager();

        $controllers = new ControllerManager($services);
        $controllers->addAbstractFactory(Factory\ResourceControllerFactory::class);

        $services->setService(ServiceLocatorInterface::class, $services);
        $services->setService('ControllerManager', $controllers);
        $services->setService('config', [
            'phlyrestfully' => [
                'resources' => [
                    'Api\ResourceController' => [
                        'listener'                   => 'CollectionIntegrationListener',
                        'page_size'                  => 3,
                        'route_name'                 => 'resource',
                        'identifier_name'            => 'id',
                        'collection_name'            => 'items',
                        'collection_query_whitelist' => 'query',
                    ],
                ],
            ],
        ]);
        $services->setInvokableClass('SharedEventManager', SharedEventManager::class);
        $services->setInvokableClass('CollectionIntegrationListener', TestAsset\CollectionIntegrationListener::class);
        $services->setFactory('EventManager', Service\EventManagerFactory::class);
        $services->setFactory('ControllerPluginManager', Service\ControllerPluginManagerFactory::class);
        $services->setShared('EventManager', false);

        $collection = $this->setUpCollection();
        $services->addDelegator(TestAsset\CollectionIntegrationListener::class, function ($container, $name, $callback) use ($collection) {
            $listener = $callback();
            $listener->setCollection($collection);
            return $listener;
        });

        return $services;
    }

    public function testCollectionLinksIncludeFullQueryString()
    {
        $this->controller->getEventManager()->attach('getList.post', function ($e) {
            $request    = $e->getTarget()->getRequest();
            $query = $request->getQuery('query', false);
            if (!$query) {
                return;
            }

            $collection = $e->getParam('collection');
            $collection->setCollectionRouteOptions([
                'query' => [
                    'query' => $query,
                ],
            ]);
        });
        $result = $this->controller->dispatch($this->request, $this->response);
        $this->assertInstanceOf(RestfulJsonModel::class, $result);

        $json = $this->renderer->render($result);
        $payload = json_decode($json, true);

        $this->assertArrayHasKey('_links', $payload);
        $links = $payload['_links'];
        foreach ($links as $name => $link) {
            $this->assertArrayHasKey('href', $link);
            if ('first' !== $name) {
                $this->assertContains(
                    'page=',
                    $link['href'],
                    "Link $name ('{$link['href']}') is missing page query param"
                );
            }
            $this->assertContains(
                'query=foo',
                $link['href'],
                "Link $name ('{$link['href']}') is missing query query param"
            );
        }
    }

    public function testFactoryEnabledListenerCreatesQueryStringWhitelist()
    {
        $services = $this->serviceManager;

        $controller = $services->get('ControllerManager')->get('Api\ResourceController');
        $controller->setEvent($this->getEvent());

        $result = $controller->dispatch($this->request, $this->response);
        $this->assertInstanceOf(RestfulJsonModel::class, $result);

        $json = $this->renderer->render($result);
        $payload = json_decode($json, true);

        $this->assertArrayHasKey('_links', $payload);
        $links = $payload['_links'];
        foreach ($links as $name => $link) {
            $this->assertArrayHasKey('href', $link);
            if ('first' !== $name) {
                $this->assertContains(
                    'page=',
                    $link['href'],
                    "Link $name ('{$link['href']}') is missing page query param"
                );
            }
            $this->assertContains(
                'query=foo',
                $link['href'],
                "Link $name ('{$link['href']}') is missing query query param"
            );
        }
    }
}
