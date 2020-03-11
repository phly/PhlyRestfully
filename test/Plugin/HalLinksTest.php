<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\Plugin;

use PhlyRestfully\HalCollection;
use PhlyRestfully\HalResource;
use PhlyRestfully\Link;
use PhlyRestfully\MetadataMap;
use PhlyRestfully\Plugin\HalLinks;
use PhlyRestfully\ResourceController;
use PHPUnit\Framework\TestCase as TestCase;
use Laminas\Http\Request;
use Laminas\Hydrator;
use Laminas\Hydrator\HydratorPluginManager;
use Laminas\Mvc\Router\Http\TreeRouteStack;
use Laminas\Mvc\Router\RouteMatch;
use Laminas\Mvc\Router\Http\Segment;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Uri\Http;
use Laminas\View\Helper\Url as UrlHelper;
use Laminas\View\Helper\ServerUrl as ServerUrlHelper;

/**
 * @subpackage UnitTest
 */
class HalLinksTest extends TestCase
{
    public function setUp(): void
    {
        $this->router = $router = new TreeRouteStack();
        $route = new Segment('/resource[/[:id]]');
        $router->addRoute('resource', $route);
        $route2 = new Segment('/help');
        $router->addRoute('docs', $route2);
        $router->addRoute('hostname', [

            'type' => 'hostname',
            'options' => [
                'route' => 'localhost.localdomain',
            ],

            'child_routes' => [
                'resource' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/resource[/:id]'
                    ],
                    'may_terminate' => true,
                    'child_routes' => [
                        'children' => [
                            'type' => 'literal',
                            'options' => [
                                'route' => '/children',
                            ],
                        ],
                    ],
                ],
                'users' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/users[/:id]'
                    ]
                ],
                'contacts' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/contacts[/:id]'
                    ]
                ],
                'embedded' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/embedded[/:id]'
                    ]
                ],
                'embedded_custom' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/embedded_custom[/:custom_id]'
                    ]
                ],
            ]
        ]);

        $this->event = $event = new MvcEvent();
        $event->setRouter($router);
        $router->setRequestUri(new Http('http://localhost.localdomain/resource'));

        $controller = $this->controller = $this->getMockBuilder(ResourceController::class)->getMock();
        $controller->expects($this->any())
            ->method('getEvent')
            ->will($this->returnValue($event));

        $this->serviceManager = new ServiceManager();

        $this->hydratorPluginManager = new HydratorPluginManager($this->serviceManager);

        $this->urlHelper = $urlHelper = new UrlHelper();
        $urlHelper->setRouter($router);

        $this->serverUrlHelper = $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $this->plugin = $plugin = new HalLinks($this->hydratorPluginManager);
        $plugin->setController($controller);
        $plugin->setUrlHelper($urlHelper);
        $plugin->setServerUrlHelper($serverUrlHelper);
    }

    public function assertRelationalLinkContains($match, $relation, $resource): void
    {
        $this->assertIsArray($resource);
        $this->assertArrayHasKey('_links', $resource);
        $links = $resource['_links'];
        $this->assertIsArray($links);
        $this->assertArrayHasKey($relation, $links);
        $link = $links[$relation];
        $this->assertIsArray($link);
        $this->assertArrayHasKey('href', $link);
        $href = $link['href'];
        $this->assertIsString($href);
        $this->assertContains($match, $href);
    }

    public function testCreateLinkSkipServerUrlHelperIfSchemeExists(): void
    {
        $url = $this->plugin->createLink('hostname/resource');
        $this->assertEquals('http://localhost.localdomain/resource', $url);
    }

    public function testLinkCreationWithoutIdCreatesFullyQualifiedLink(): void
    {
        $url = $this->plugin->createLink('resource');
        $this->assertEquals('http://localhost.localdomain/resource', $url);
    }

    public function testLinkCreationWithIdCreatesFullyQualifiedLink(): void
    {
        $url = $this->plugin->createLink('resource', 123);
        $this->assertEquals('http://localhost.localdomain/resource/123', $url);
    }

    public function testLinkCreationFromHalResource(): void
    {
        $self = new Link('self');
        $self->setRoute('resource', ['id' => 123]);
        $docs = new Link('describedby');
        $docs->setRoute('docs');
        $resource = new HalResource([], 123);
        $resource->getLinks()->add($self)->add($docs);
        $links = $this->plugin->fromResource($resource);

        $this->assertIsArray($links);
        $this->assertArrayHasKey('self', $links, var_export($links, true));
        $this->assertArrayHasKey('describedby', $links, var_export($links, true));

        $selfLink = $links['self'];
        $this->assertIsArray($selfLink);
        $this->assertArrayHasKey('href', $selfLink);
        $this->assertEquals('http://localhost.localdomain/resource/123', $selfLink['href']);

        $docsLink = $links['describedby'];
        $this->assertIsArray($docsLink);
        $this->assertArrayHasKey('href', $docsLink);
        $this->assertEquals('http://localhost.localdomain/help', $docsLink['href']);
    }

    public function testRendersEmbeddedCollectionsInsideResources(): void
    {
        $collection = new HalCollection(
            [
                (object) ['id' => 'foo', 'name' => 'foo'],
                (object) ['id' => 'bar', 'name' => 'bar'],
                (object) ['id' => 'baz', 'name' => 'baz'],
            ],
            'hostname/contacts'
        );
        $resource = new HalResource(
            (object) [
                'id'       => 'user',
                'contacts' => $collection,
            ],
            'user'
        );
        $self = new Link('self');
        $self->setRoute('hostname/users', ['id' => 'user']);
        $resource->getLinks()->add($self);

        $rendered = $this->plugin->renderResource($resource);
        $this->assertRelationalLinkContains('/users/', 'self', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertArrayHasKey('contacts', $embed);
        $contacts = $embed['contacts'];
        $this->assertIsArray($contacts);
        $this->assertCount(3, $contacts);
        foreach ($contacts as $contact) {
            $this->assertIsArray($contact);
            $this->assertRelationalLinkContains('/contacts/', 'self', $contact);
        }
    }

    public function testRendersEmbeddedResourcesInsideResourcesBasedOnMetadataMap(): void
    {
        $object = new TestAsset\Resource('foo', 'Foo');
        $object->first_child  = new TestAsset\EmbeddedResource('bar', 'Bar');
        $object->second_child = new TestAsset\EmbeddedResourceWithCustomIdentifier('baz', 'Baz');
        $resource = new HalResource($object, 'foo');
        $self = new Link('self');
        $self->setRoute('hostname/resource', ['id' => 'foo']);
        $resource->getLinks()->add($self);

        $metadata = new MetadataMap([
            TestAsset\Resource::class => [
                'hydrator' => Hydrator\ObjectProperty::class,
                'route'    => 'hostname/resource',
            ],
            TestAsset\EmbeddedResource::class => [
                'hydrator' => Hydrator\ObjectProperty::class,
                'route'    => 'hostname/embedded',
            ],
            TestAsset\EmbeddedResourceWithCustomIdentifier::class => [
                'hydrator'        => Hydrator\ObjectProperty::class,
                'route'           => 'hostname/embedded_custom',
                'identifier_name' => 'custom_id',
            ],
        ], $this->hydratorPluginManager);

        $this->plugin->setMetadataMap($metadata);

        $rendered = $this->plugin->renderResource($resource);
        $this->assertRelationalLinkContains('/resource/foo', 'self', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertCount(2, $embed);
        $this->assertArrayHasKey('first_child', $embed);
        $this->assertArrayHasKey('second_child', $embed);

        $first = $embed['first_child'];
        $this->assertIsArray($first);
        $this->assertRelationalLinkContains('/embedded/bar', 'self', $first);

        $second = $embed['second_child'];
        $this->assertIsArray($second);
        $this->assertRelationalLinkContains('/embedded_custom/baz', 'self', $second);
    }

    public function testRendersEmbeddedCollectionsInsideResourcesBasedOnMetadataMap(): void
    {
        $collection = new TestAsset\Collection(
            [
                (object) ['id' => 'foo', 'name' => 'foo'],
                (object) ['id' => 'bar', 'name' => 'bar'],
                (object) ['id' => 'baz', 'name' => 'baz'],
            ]
        );

        $metadata = new MetadataMap([
            TestAsset\Collection::class => [
                'is_collection'  => true,
                'route'          => 'hostname/contacts',
                'resource_route' => 'hostname/embedded',
            ],
        ], $this->hydratorPluginManager);

        $this->plugin->setMetadataMap($metadata);

        $resource = new HalResource(
            (object) [
                'id'       => 'user',
                'contacts' => $collection,
            ],
            'user'
        );
        $self = new Link('self');
        $self->setRoute('hostname/users', ['id' => 'user']);
        $resource->getLinks()->add($self);

        $rendered = $this->plugin->renderResource($resource);

        $this->assertRelationalLinkContains('/users/', 'self', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertArrayHasKey('contacts', $embed);
        $contacts = $embed['contacts'];
        $this->assertIsArray($contacts);
        $this->assertCount(3, $contacts);
        foreach ($contacts as $contact) {
            $this->assertIsArray($contact);
            $this->assertArrayHasKey('id', $contact);
            $this->assertRelationalLinkContains('/embedded/' . $contact['id'], 'self', $contact);
        }
    }

    public function testRendersEmbeddedCollectionsInsideCollectionsBasedOnMetadataMap(): void
    {
        $childCollection = new TestAsset\Collection(
            [
                (object) ['id' => 'foo', 'name' => 'foo'],
                (object) ['id' => 'bar', 'name' => 'bar'],
                (object) ['id' => 'baz', 'name' => 'baz'],
            ]
        );
        $resource = new TestAsset\Resource('spock', 'Spock');
        $resource->first_child = $childCollection;

        $metadata = new MetadataMap([
            TestAsset\Collection::class => [
                'is_collection'  => true,
                'route'          => 'hostname/contacts',
                'resource_route' => 'hostname/embedded',
            ],
            TestAsset\Resource::class => [
                'hydrator' => Hydrator\ObjectProperty::class,
                'route'    => 'hostname/resource',
            ],
        ], $this->hydratorPluginManager);

        $this->plugin->setMetadataMap($metadata);

        $collection = new HalCollection([$resource], 'hostname/resource');
        $self = new Link('self');
        $self->setRoute('hostname/resource');
        $collection->getLinks()->add($self);
        $collection->setCollectionName('resources');

        $rendered = $this->plugin->renderCollection($collection);

        $this->assertRelationalLinkContains('/resource', 'self', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertArrayHasKey('resources', $embed);
        $resources = $embed['resources'];
        $this->assertIsArray($resources);
        $this->assertCount(1, $resources);

        $resource = array_shift($resources);
        $this->assertIsArray($resource);
        $this->assertArrayHasKey('_embedded', $resource);
        $this->assertIsArray($resource['_embedded']);
        $this->assertArrayHasKey('first_child', $resource['_embedded']);
        $this->assertIsArray($resource['_embedded']['first_child']);

        foreach ($resource['_embedded']['first_child'] as $contact) {
            $this->assertIsArray($contact);
            $this->assertArrayHasKey('id', $contact);
            $this->assertRelationalLinkContains('/embedded/' . $contact['id'], 'self', $contact);
        }
    }

    public function testWillNotAllowInjectingASelfRelationMultipleTimes(): void
    {
        $resource = new HalResource([
            'id'  => 1,
            'foo' => 'bar',
        ], 1);
        $links = $resource->getLinks();

        $this->assertFalse($links->has('self'));

        $this->plugin->injectSelfLink($resource, 'hostname/resource');

        $this->assertTrue($links->has('self'));
        $link = $links->get('self');
        $this->assertInstanceof(Link::class, $link);

        $this->plugin->injectSelfLink($resource, 'hostname/resource');
        $this->assertTrue($links->has('self'));
        $link = $links->get('self');
        $this->assertInstanceof(Link::class, $link);
    }

    /**
     * @group 71
     */
    public function testRenderingEmbeddedHalResourceEmbedsResource(): void
    {
        $embedded = new HalResource((object) ['id' => 'foo', 'name' => 'foo'], 'foo');
        $self = new Link('self');
        $self->setRoute('hostname/contacts', ['id' => 'foo']);
        $embedded->getLinks()->add($self);

        $resource = new HalResource((object) ['id' => 'user', 'contact' => $embedded], 'user');
        $self = new Link('self');
        $self->setRoute('hostname/users', ['id' => 'user']);
        $resource->getLinks()->add($self);

        $rendered = $this->plugin->renderResource($resource);

        $this->assertRelationalLinkContains('/users/user', 'self', $rendered);
        $this->assertArrayHasKey('_embedded', $rendered);
        $this->assertIsArray($rendered['_embedded']);
        $this->assertArrayHasKey('contact', $rendered['_embedded']);
        $contact = $rendered['_embedded']['contact'];
        $this->assertRelationalLinkContains('/contacts/foo', 'self', $contact);
    }

    /**
     * @group 71
     */
    public function testRenderingCollectionRendersAllLinksInEmbeddedResources(): void
    {
        $embedded = new HalResource((object) ['id' => 'foo', 'name' => 'foo'], 'foo');
        $links = $embedded->getLinks();
        $self = new Link('self');
        $self->setRoute('hostname/users', ['id' => 'foo']);
        $links->add($self);
        $phones = new Link('phones');
        $phones->setUrl('http://localhost.localdomain/users/foo/phones');
        $links->add($phones);

        $collection = new HalCollection([$embedded]);
        $collection->setCollectionName('users');
        $self = new Link('self');
        $self->setRoute('hostname/users');
        $collection->getLinks()->add($self);

        $rendered = $this->plugin->renderCollection($collection);

        $this->assertRelationalLinkContains('/users', 'self', $rendered);
        $this->assertArrayHasKey('_embedded', $rendered);
        $this->assertIsArray($rendered['_embedded']);
        $this->assertArrayHasKey('users', $rendered['_embedded']);

        $users = $rendered['_embedded']['users'];
        $this->assertIsArray($users);
        $user = array_shift($users);

        $this->assertRelationalLinkContains('/users/foo', 'self', $user);
        $this->assertRelationalLinkContains('/users/foo/phones', 'phones', $user);
    }

    public function testRenderingCollectionRendersAllLinksInEmbeddedArrayResourcesWithCustomIdentifier(): void
    {
        $embedded = ['custom_id' => 'foo', 'name' => 'foo'];

        $collection = new HalCollection([$embedded]);
        $collection->setCollectionName('embedded_custom');
        $collection->setCollectionRoute('hostname/embedded_custom');
        $collection->setResourceRoute('hostname/embedded_custom');
        $collection->setIdentifierName('custom_id');
        $self = new Link('self');
        $self->setRoute('hostname/embedded_custom');
        $collection->getLinks()->add($self);

        $rendered = $this->plugin->renderCollection($collection);

        $this->assertRelationalLinkContains('/embedded_custom', 'self', $rendered);
        $this->assertArrayHasKey('_embedded', $rendered);
        $this->assertIsArray($rendered['_embedded']);
        $this->assertArrayHasKey('embedded_custom', $rendered['_embedded']);

        $embeddedCustoms = $rendered['_embedded']['embedded_custom'];
        $this->assertIsArray($embeddedCustoms);
        $embeddedCustom = array_shift($embeddedCustoms);

        $this->assertRelationalLinkContains('/embedded_custom/foo', 'self', $embeddedCustom);
    }

    public function testResourcesFromCollectionCanUseHydratorSetInMetadataMap(): void
    {
        $object   = new TestAsset\ResourceWithProtectedProperties('foo', 'Foo');
        $resource = new HalResource($object, 'foo');

        $metadata = new MetadataMap([
            TestAsset\ResourceWithProtectedProperties::class => [
                'hydrator' => 'ArraySerializable',
                'route'    => 'hostname/resource',
            ],
        ], $this->hydratorPluginManager);

        $collection = new HalCollection([$resource]);
        $collection->setCollectionName('resource');
        $collection->setCollectionRoute('hostname/resource');

        $this->plugin->setMetadataMap($metadata);

        $test = $this->plugin->renderCollection($collection);

        $this->assertIsArray($test);
        $this->assertArrayHasKey('_embedded', $test);
        $this->assertIsArray($test['_embedded']);
        $this->assertArrayHasKey('resource', $test['_embedded']);
        $this->assertIsArray($test['_embedded']['resource']);

        $resources = $test['_embedded']['resource'];
        $testResource = array_shift($resources);
        $this->assertIsArray($testResource);
        $this->assertArrayHasKey('id', $testResource);
        $this->assertArrayHasKey('name', $testResource);
    }

    /**
     * @group 79
     */
    public function testInjectsLinksFromMetadataWhenCreatingResource(): void
    {
        $object = new TestAsset\Resource('foo', 'Foo');
        $resource = new HalResource($object, 'foo');

        $metadata = new MetadataMap([
            TestAsset\Resource::class => [
                'hydrator' => Hydrator\ObjectProperty::class,
                'route'    => 'hostname/resource',
                'links'    => [
                    [
                        'rel' => 'describedby',
                        'url' => 'http://example.com/api/help/resource',
                    ],
                    [
                        'rel' => 'children',
                        'route' => [
                            'name' => 'resource/children',
                        ],
                    ],
                ],
            ],
        ], $this->hydratorPluginManager);

        $this->plugin->setMetadataMap($metadata);
        $resource = $this->plugin->createResourceFromMetadata(
            $object,
            $metadata->get(TestAsset\Resource::class)
        );
        $this->assertInstanceof(HalResource::class, $resource);
        $links = $resource->getLinks();
        $this->assertTrue($links->has('describedby'));
        $this->assertTrue($links->has('children'));

        $describedby = $links->get('describedby');
        $this->assertTrue($describedby->hasUrl());
        $this->assertEquals('http://example.com/api/help/resource', $describedby->getUrl());

        $children = $links->get('children');
        $this->assertTrue($children->hasRoute());
        $this->assertEquals('resource/children', $children->getRoute());
    }

    /**
     * @group 79
     */
    public function testInjectsLinksFromMetadataWhenCreatingCollection(): void
    {
        $set = new TestAsset\Collection(
            [
                (object) ['id' => 'foo', 'name' => 'foo'],
                (object) ['id' => 'bar', 'name' => 'bar'],
                (object) ['id' => 'baz', 'name' => 'baz'],
            ]
        );

        $metadata = new MetadataMap([
            TestAsset\Collection::class => [
                'is_collection'  => true,
                'route'          => 'hostname/contacts',
                'resource_route' => 'hostname/embedded',
                'links'          => [
                    [
                        'rel' => 'describedby',
                        'url' => 'http://example.com/api/help/collection',
                    ],
                ],
            ],
        ], $this->hydratorPluginManager);

        $this->plugin->setMetadataMap($metadata);

        $collection = $this->plugin->createCollectionFromMetadata(
            $set,
            $metadata->get(TestAsset\Collection::class)
        );
        $this->assertInstanceof(HalCollection::class, $collection);
        $links = $collection->getLinks();
        $this->assertTrue($links->has('describedby'));
        $link = $links->get('describedby');
        $this->assertTrue($link->hasUrl());
        $this->assertEquals('http://example.com/api/help/collection', $link->getUrl());
    }

    /**
     * @group 79
     */
    public function testRenderResourceTriggersEvent(): void
    {
        $resource = new HalResource(
            (object) [
                'id'   => 'user',
                'name' => 'matthew',
            ],
            'user'
        );
        $self = new Link('self');
        $self->setRoute('hostname/users', ['id' => 'user']);
        $resource->getLinks()->add($self);

        $this->plugin->getEventManager()->attach('renderResource', function ($e): void {
            $resource = $e->getParam('resource');
            $resource->getLinks()->get('self')->setRouteParams(['id' => 'matthew']);
        });

        $rendered = $this->plugin->renderResource($resource);
        $this->assertContains('/users/matthew', $rendered['_links']['self']['href']);
    }

    /**
     * @group 79
     */
    public function testRenderCollectionTriggersEvent(): void
    {
        $collection = new HalCollection(
            [
                (object) ['id' => 'foo', 'name' => 'foo'],
                (object) ['id' => 'bar', 'name' => 'bar'],
                (object) ['id' => 'baz', 'name' => 'baz'],
            ],
            'hostname/contacts'
        );
        $self = new Link('self');
        $self->setRoute('hostname/contacts');
        $collection->getLinks()->add($self);
        $collection->setCollectionName('resources');

        $this->plugin->getEventManager()->attach('renderCollection', function ($e): void {
            $collection = $e->getParam('collection');
            $collection->setAttributes(['injected' => true]);
        });

        $rendered = $this->plugin->renderCollection($collection);
        $this->assertArrayHasKey('injected', $rendered);
        $this->assertTrue($rendered['injected']);
    }

    public function matchUrl($url)
    {
        $url     = 'http://localhost.localdomain' . $url;
        $request = new Request();
        $request->setUri($url);

        $match = $this->router->match($request);
        if ($match instanceof RouteMatch) {
            $this->urlHelper->setRouteMatch($match);
        }

        return $match;
    }

    /**
     * @group 95
     */
    public function testPassingFalseReuseParamsOptionShouldOmitMatchedParametersInGeneratedLink(): void
    {
        $matches = $this->matchUrl('/resource/foo');
        $this->assertEquals('foo', $matches->getParam('id', false));

        $link = Link::factory([
            'rel' => 'resource',
            'route' => [
                'name' => 'hostname/resource',
                'options' => [
                    'reuse_matched_params' => false,
                ],
            ],
        ]);
        $result = $this->plugin->fromLink($link);
        $expected = [
            'href' => 'http://localhost.localdomain/resource',
        ];
        $this->assertEquals($expected, $result);
    }
}
