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
        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertArrayHasKey('contacts', $embed);
        $contacts = $embed['contacts'];
        $this->assertInternalType('array', $contacts);
        $this->assertEquals(3, count($contacts));
        foreach ($contacts as $contact) {
            $this->assertInternalType('array', $contact);
            $this->assertArrayHasKey('_links', $contact);
            $this->assertArrayHasKey('self', $contact['_links']);
            $this->assertArrayHasKey('href', $contact['_links']['self']);
            $this->assertContains('/contacts/', $contact['_links']['self']['href']);
        }
    }
}
