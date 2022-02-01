<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\Listener;

use PhlyRestfully\Listener\ResourceParametersListener;
use PhlyRestfully\Resource;
use PhlyRestfully\ResourceController;
use PHPUnit\Framework\TestCase as TestCase;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\RouteMatch;
use Laminas\Stdlib\Parameters;

/**
 * @subpackage UnitTest
 */
class ResourceParametersListenerTest extends TestCase
{
    public function setUp(): void
    {
        $this->resource   = $resource   = new Resource();
        $this->controller = $controller = new ResourceController();
        $controller->setResource($resource);

        $this->matches    = $matches    = new RouteMatch([]);
        $this->query      = $query      = new Parameters();
        $this->request    = $request    = new Request();
        $request->setQuery($query);

        $this->event    = new MvcEvent();
        $this->event->setTarget($controller);
        $this->event->setRouteMatch($matches);
        $this->event->setRequest($request);

        $this->listener = new ResourceParametersListener();
    }

    public function testIgnoresNonResourceControllers(): void
    {
        $controller = $this->getMockBuilder('Laminas\Mvc\Controller\AbstractRestfulController')->getMock();
        $this->event->setTarget($controller);
        $this->listener->onDispatch($this->event);
        $this->assertNull($this->resource->getRouteMatch());
        $this->assertNull($this->resource->getQueryParams());
    }

    public function testInjectsRouteMatchOnDispatchOfResourceController(): void
    {
        $this->listener->onDispatch($this->event);
        $this->assertSame($this->matches, $this->resource->getRouteMatch());
    }

    public function testInjectsQueryParamsOnDispatchOfResourceController(): void
    {
        $this->listener->onDispatch($this->event);
        $this->assertSame($this->query, $this->resource->getQueryParams());
    }
}
