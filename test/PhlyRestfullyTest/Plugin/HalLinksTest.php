<?php
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
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\Mvc\Router\SimpleRouteStack;
use Zend\Mvc\Router\Http\Segment;
use Zend\Mvc\MvcEvent;
use Zend\Uri\Http;
use Zend\Uri\Uri;
use Zend\View\Helper\Url as UrlHelper;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;

/**
 * @subpackage UnitTest
 */
class HalLinksTest extends TestCase
{
    public function setUp()
    {
        $this->router = $router = new TreeRouteStack();
        $route = new Segment('/resource[/[:id]]');
        $router->addRoute('resource', $route);
        $route2 = new Segment('/help');
        $router->addRoute('docs', $route2);
        $router->addRoute('hostname', array(

            'type' => 'hostname',
            'options' => array(
                'route' => 'localhost.localdomain',
            ),

            'child_routes' => array(
                'resource' => array(
                    'type' => 'segment',
                    'options' => array(
                        'route' => '/resource[/:id]'
                    )
                ),
                'users' => array(
                    'type' => 'segment',
                    'options' => array(
                        'route' => '/users[/:id]'
                    )
                ),
                'contacts' => array(
                    'type' => 'segment',
                    'options' => array(
                        'route' => '/contacts[/:id]'
                    )
                ),
                'embedded' => array(
                    'type' => 'segment',
                    'options' => array(
                        'route' => '/embedded[/:id]'
                    )
                ),
                'embedded_custom' => array(
                    'type' => 'segment',
                    'options' => array(
                        'route' => '/embedded_custom[/:custom_id]'
                    )
                ),
            )
        ));

        $this->event = $event = new MvcEvent();
        $event->setRouter($router);
        $router->setRequestUri(new Http('http://localhost.localdomain/resource'));

        $controller = $this->controller = $this->getMock('PhlyRestfully\ResourceController');
        $controller->expects($this->any())
            ->method('getEvent')
            ->will($this->returnValue($event));

        $this->urlHelper = $urlHelper = new UrlHelper();
        $urlHelper->setRouter($router);

        $this->serverUrlHelper = $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $this->plugin = $plugin = new HalLinks();
        $plugin->setController($controller);
        $plugin->setUrlHelper($urlHelper);
        $plugin->setServerUrlHelper($serverUrlHelper);
    }

    public function assertLink($rel, $matches, array $resource)
    {
        $this->assertArrayHasKey('_links', $resource);
        $this->assertInternalType('array', $resource['_links']);
        $this->assertArrayHasKey($rel, $resource['_links']);
        $this->assertInternalType('array', $resource['_links'][$rel]);
        $this->assertArrayHasKey('href', $resource['_links'][$rel]);
        $this->assertInternalType('string', $resource['_links'][$rel]['href']);
        $this->assertContains($matches, $resource['_links'][$rel]['href']);
    }

    public function testCreateLinkSkipServerUrlHelperIfSchemeExists()
    {
        $url = $this->plugin->createLink('hostname/resource');
        $this->assertEquals('http://localhost.localdomain/resource', $url);
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

    public function testLinkCreationFromHalResource()
    {
        $self = new Link('self');
        $self->setRoute('resource', array('id' => 123));
        $docs = new Link('describedby');
        $docs->setRoute('docs');
        $resource = new HalResource(array(), 123);
        $resource->getLinks()->add($self)->add($docs);
        $links = $this->plugin->fromResource($resource);

        $this->assertInternalType('array', $links);
        $this->assertArrayHasKey('self', $links, var_export($links, 1));
        $this->assertArrayHasKey('describedby', $links, var_export($links, 1));

        $selfLink = $links['self'];
        $this->assertInternalType('array', $selfLink);
        $this->assertArrayHasKey('href', $selfLink);
        $this->assertEquals('http://localhost.localdomain/resource/123', $selfLink['href']);

        $docsLink = $links['describedby'];
        $this->assertInternalType('array', $docsLink);
        $this->assertArrayHasKey('href', $docsLink);
        $this->assertEquals('http://localhost.localdomain/help', $docsLink['href']);
    }

    public function testRendersEmbeddedCollectionsInsideResources()
    {
        $collection = new HalCollection(
            array(
                (object) array('id' => 'foo', 'name' => 'foo'),
                (object) array('id' => 'bar', 'name' => 'bar'),
                (object) array('id' => 'baz', 'name' => 'baz'),
            ),
            'hostname/contacts'
        );
        $resource = new HalResource(
            (object) array(
                'id'       => 'user',
                'contacts' => $collection,
            ),
            'user'
        );
        $self = new Link('self');
        $self->setRoute('hostname/users', array('id' => 'user'));
        $resource->getLinks()->add($self);

        $rendered = $this->plugin->renderResource($resource);
        $this->assertLink('self', '/users/', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertArrayHasKey('contacts', $embed);
        $contacts = $embed['contacts'];
        $this->assertInternalType('array', $contacts);
        $this->assertEquals(3, count($contacts));
        foreach ($contacts as $contact) {
            $this->assertInternalType('array', $contact);
            $this->assertLink('self', '/contacts/', $contact);
        }
    }

    public function testRendersEmbeddedResourcesInsideResourcesBasedOnMetadataMap()
    {
        $object = new TestAsset\Resource('foo', 'Foo');
        $object->first_child  = new TestAsset\EmbeddedResource('bar', 'Bar');
        $object->second_child = new TestAsset\EmbeddedResourceWithCustomIdentifier('baz', 'Baz');
        $resource = new HalResource($object, 'foo');
        $self = new Link('self');
        $self->setRoute('hostname/resource', array('id' => 'foo'));
        $resource->getLinks()->add($self);

        $metadata = new MetadataMap(array(
            'PhlyRestfullyTest\Plugin\TestAsset\Resource' => array(
                'hydrator' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'    => 'hostname/resource',
            ),
            'PhlyRestfullyTest\Plugin\TestAsset\EmbeddedResource' => array(
                'hydrator' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
            ),
            'PhlyRestfullyTest\Plugin\TestAsset\EmbeddedResourceWithCustomIdentifier' => array(
                'hydrator'        => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'           => 'hostname/embedded_custom',
                'identifier_name' => 'custom_id',
            ),
        ));

        $this->plugin->setMetadataMap($metadata);

        $rendered = $this->plugin->renderResource($resource);
        $this->assertLink('self', '/resource/foo', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertEquals(2, count($embed));
        $this->assertArrayHasKey('first_child', $embed);
        $this->assertArrayHasKey('second_child', $embed);

        $first = $embed['first_child'];
        $this->assertInternalType('array', $first);
        $this->assertLink('self', '/embedded/bar', $first);

        $second = $embed['second_child'];
        $this->assertInternalType('array', $second);
        $this->assertLink('self', '/embedded_custom/baz', $second);
    }

    public function testRendersEmbeddedCollectionsInsideResourcesBasedOnMetadataMap()
    {
        $collection = new TestAsset\Collection(
            array(
                (object) array('id' => 'foo', 'name' => 'foo'),
                (object) array('id' => 'bar', 'name' => 'bar'),
                (object) array('id' => 'baz', 'name' => 'baz'),
            )
        );

        $metadata = new MetadataMap(array(
            'PhlyRestfullyTest\Plugin\TestAsset\Collection' => array(
                'is_collection'  => true,
                'route'          => 'hostname/contacts',
                'resource_route' => 'hostname/embedded',
            ),
        ));

        $this->plugin->setMetadataMap($metadata);

        $resource = new HalResource(
            (object) array(
                'id'       => 'user',
                'contacts' => $collection,
            ),
            'user'
        );
        $self = new Link('self');
        $self->setRoute('hostname/users', array('id' => 'user'));
        $resource->getLinks()->add($self);

        $rendered = $this->plugin->renderResource($resource);

        $this->assertLink('self', '/users/', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertArrayHasKey('contacts', $embed);
        $contacts = $embed['contacts'];
        $this->assertInternalType('array', $contacts);
        $this->assertEquals(3, count($contacts));
        foreach ($contacts as $contact) {
            $this->assertInternalType('array', $contact);
            $this->assertArrayHasKey('id', $contact);
            $this->assertLink('self', '/embedded/' . $contact['id'], $contact);
        }
    }

    public function testRendersEmbeddedCollectionsInsideCollectionsBasedOnMetadataMap()
    {
        $childCollection = new TestAsset\Collection(
            array(
                (object) array('id' => 'foo', 'name' => 'foo'),
                (object) array('id' => 'bar', 'name' => 'bar'),
                (object) array('id' => 'baz', 'name' => 'baz'),
            )
        );
        $resource = new TestAsset\Resource('spock', 'Spock');
        $resource->first_child = $childCollection;

        $metadata = new MetadataMap(array(
            'PhlyRestfullyTest\Plugin\TestAsset\Collection' => array(
                'is_collection'  => true,
                'route'          => 'hostname/contacts',
                'resource_route' => 'hostname/embedded',
            ),
            'PhlyRestfullyTest\Plugin\TestAsset\Resource' => array(
                'hydrator' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'    => 'hostname/resource',
            ),
        ));

        $this->plugin->setMetadataMap($metadata);

        $collection = new HalCollection(array($resource), 'hostname/resource');
        $self = new Link('self');
        $self->setRoute('hostname/resource');
        $collection->getLinks()->add($self);
        $collection->setCollectionName('resources');

        $rendered = $this->plugin->renderCollection($collection);

        $this->assertLink('self', '/resource', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertArrayHasKey('resources', $embed);
        $resources = $embed['resources'];
        $this->assertInternalType('array', $resources);
        $this->assertEquals(1, count($resources));

        $resource = array_shift($resources);
        $this->assertInternalType('array', $resource);
        $this->assertArrayHasKey('_embedded', $resource);
        $this->assertInternalType('array', $resource['_embedded']);
        $this->assertArrayHasKey('first_child', $resource['_embedded']);
        $this->assertInternalType('array', $resource['_embedded']['first_child']);

        foreach ($resource['_embedded']['first_child'] as $contact) {
            $this->assertInternalType('array', $contact);
            $this->assertArrayHasKey('id', $contact);
            $this->assertLink('self', '/embedded/' . $contact['id'], $contact);
        }
    }

    public function testWillNotAllowInjectingASelfRelationMultipleTimes()
    {
        $resource = new HalResource(array(
            'id'  => 1,
            'foo' => 'bar',
        ), 1);
        $links = $resource->getLinks();

        $this->assertFalse($links->has('self'));

        $this->plugin->injectSelfLink($resource, 'hostname/resource');

        $this->assertTrue($links->has('self'));
        $link = $links->get('self');
        $this->assertInstanceof('PhlyRestfully\Link', $link);

        $this->plugin->injectSelfLink($resource, 'hostname/resource');
        $this->assertTrue($links->has('self'));
        $link = $links->get('self');
        $this->assertInstanceof('PhlyRestfully\Link', $link);
    }
}
