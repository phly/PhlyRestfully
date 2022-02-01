<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\ResourceEvent;
use PHPUnit\Framework\TestCase as TestCase;
use Laminas\Router\RouteMatch;
use Laminas\Stdlib\Parameters;

class ResourceEventTest extends TestCase
{
    public function setUp(): void
    {
        $this->matches = new RouteMatch([
            'foo' => 'bar',
            'baz' => 'inga',
        ]);
        $this->query = new Parameters([
            'foo' => 'bar',
            'baz' => 'inga',
        ]);

        $this->event = new ResourceEvent();
    }

    public function testRouteMatchIsNullByDefault(): void
    {
        $this->assertNull($this->event->getRouteMatch());
    }

    public function testQueryParamsAreNullByDefault(): void
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
    public function testRouteMatchIsNullable(ResourceEvent $event): void
    {
        $event->setRouteMatch(null);
        $this->assertNull($event->getRouteMatch());
    }

    /**
     * @depends testQueryParamsAreMutable
     */
    public function testQueryParamsAreNullable(ResourceEvent $event): void
    {
        $event->setQueryParams(null);
        $this->assertNull($event->getQueryParams());
    }

    public function testCanFetchIndividualRouteParameter(): void
    {
        $this->event->setRouteMatch($this->matches);
        $this->assertEquals('bar', $this->event->getRouteParam('foo'));
        $this->assertEquals('inga', $this->event->getRouteParam('baz'));
    }

    public function testCanFetchIndividualQueryParameter(): void
    {
        $this->event->setQueryParams($this->query);
        $this->assertEquals('bar', $this->event->getQueryParam('foo'));
        $this->assertEquals('inga', $this->event->getQueryParam('baz'));
    }

    public function testReturnsDefaultParameterWhenPullingUnknownRouteParameter(): void
    {
        $this->assertNull($this->event->getRouteParam('foo'));
        $this->assertEquals('bat', $this->event->getRouteParam('baz', 'bat'));
    }

    public function testReturnsDefaultParameterWhenPullingUnknownQueryParameter(): void
    {
        $this->assertNull($this->event->getQueryParam('foo'));
        $this->assertEquals('bat', $this->event->getQueryParam('baz', 'bat'));
    }
}
