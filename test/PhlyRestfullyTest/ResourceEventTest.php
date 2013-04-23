<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\ResourceEvent;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Mvc\Router\RouteMatch;
use Zend\Stdlib\Parameters;

class ResourceEventTest extends TestCase
{
    public function setUp()
    {
        $this->matches = new RouteMatch(array(
            'foo' => 'bar',
            'baz' => 'inga',
        ));
        $this->query = new Parameters(array(
            'foo' => 'bar',
            'baz' => 'inga',
        ));

        $this->event = new ResourceEvent();
    }

    public function testRouteMatchIsNullByDefault()
    {
        $this->assertNull($this->event->getRouteMatch());
    }

    public function testQueryParamsAreNullByDefault()
    {
        $this->assertNull($this->event->getQueryParams());
    }

    public function testRouteMatchIsMutable()
    {
        $this->event->setRouteMatch($this->matches);
        $this->assertSame($this->matches, $this->event->getRouteMatch());
        return $this->event;
    }

    public function testQueryParamsAreMutable()
    {
        $this->event->setQueryParams($this->query);
        $this->assertSame($this->query, $this->event->getQueryParams());
        return $this->event;
    }

    /**
     * @depends testRouteMatchIsMutable
     */
    public function testRouteMatchIsNullable(ResourceEvent $event)
    {
        $event->setRouteMatch(null);
        $this->assertNull($event->getRouteMatch());
    }

    /**
     * @depends testQueryParamsAreMutable
     */
    public function testQueryParamsAreNullable(ResourceEvent $event)
    {
        $event->setQueryParams(null);
        $this->assertNull($event->getQueryParams());
    }

    public function testCanFetchIndividualRouteParameter()
    {
        $this->event->setRouteMatch($this->matches);
        $this->assertEquals('bar', $this->event->getRouteParam('foo'));
        $this->assertEquals('inga', $this->event->getRouteParam('baz'));
    }

    public function testCanFetchIndividualQueryParameter(/* ResourceEvent $event */)
    {
        $this->event->setQueryParams($this->query);
        $this->assertEquals('bar', $this->event->getQueryParam('foo'));
        $this->assertEquals('inga', $this->event->getQueryParam('baz'));
    }

    public function testReturnsDefaultParameterWhenPullingUnknownRouteParameter()
    {
        $this->assertNull($this->event->getRouteParam('foo'));
        $this->assertEquals('bat', $this->event->getRouteParam('baz', 'bat'));
    }

    public function testReturnsDefaultParameterWhenPullingUnknownQueryParameter()
    {
        $this->assertNull($this->event->getQueryParam('foo'));
        $this->assertEquals('bat', $this->event->getQueryParam('baz', 'bat'));
    }
}
