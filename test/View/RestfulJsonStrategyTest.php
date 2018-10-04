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
use PhlyRestfully\HalResource;
use PhlyRestfully\Link;
use PhlyRestfully\View\RestfulJsonModel;
use PhlyRestfully\View\RestfulJsonRenderer;
use PhlyRestfully\View\RestfulJsonStrategy;
use PHPUnit\Framework\TestCase as TestCase;
use Zend\Http\Response;
use Zend\View\Renderer\JsonRenderer;
use Zend\View\ViewEvent;

/**
 * @subpackage UnitTest
 */
class RestfulJsonStrategyTest extends TestCase
{
    public function setUp()
    {
        $this->response = new Response;
        $this->event    = new ViewEvent;
        $this->event->setResponse($this->response);

        $this->renderer = new RestfulJsonRenderer;
        $this->strategy = new RestfulJsonStrategy($this->renderer);
    }

    public function testSelectRendererReturnsNullIfModelIsNotARestfulJsonModel()
    {
        $this->assertNull($this->strategy->selectRenderer($this->event));
    }

    public function testSelectRendererReturnsRendererIfModelIsARestfulJsonModel()
    {
        $model = new RestfulJsonModel();
        $this->event->setModel($model);
        $this->assertSame($this->renderer, $this->strategy->selectRenderer($this->event));
    }

    public function testInjectResponseDoesNotSetContentTypeHeaderIfRendererDoesNotMatch()
    {
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertFalse($headers->has('Content-Type'));
    }

    public function testInjectResponseDoesNotSetContentTypeHeaderIfResultIsNotString()
    {
        $this->event->setRenderer($this->renderer);
        $this->event->setResult(['foo']);
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertFalse($headers->has('Content-Type'));
    }

    public function testInjectResponseSetsContentTypeHeaderToDefaultIfNotProblemApiOrHalModel()
    {
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/json', $header->getFieldValue());
    }

    public function testInjectResponseSetsContentTypeHeaderToApiProblemForApiProblemModel()
    {
        $problem = new ApiProblem(500, 'whatever', 'foo', 'bar');
        $model   = new RestfulJsonModel(['payload' => $problem]);
        $this->event->setModel($model);
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/api-problem+json', $header->getFieldValue());
    }

    public function halObjects()
    {
        $resource = new HalResource([
            'foo' => 'bar',
        ], 'identifier', 'route');
        $link = new Link('self');
        $link->setRoute('resource/route')->setRouteParams(['id' => 'identifier']);
        $resource->getLinks()->add($link);

        $collection = new HalCollection([$resource]);
        $collection->setCollectionRoute('collection/route');
        $collection->setResourceRoute('resource/route');

        return [
            'resource'   => [$resource],
            'collection' => [$collection],
        ];
    }

    /**
     * @dataProvider halObjects
     */
    public function testInjectResponseSetsContentTypeHeaderToHalForHalModel($hal)
    {
        $model = new RestfulJsonModel(['payload' => $hal]);

        $this->event->setModel($model);
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/hal+json', $header->getFieldValue());
    }

    public function invalidStatusCodes()
    {
        return [
            [0],
            [1],
            [99],
            [600],
            [10081],
        ];
    }

    /**
     * @dataProvider invalidStatusCodes
     */
    public function testUsesStatusCode500ForAnyStatusCodesAbove599OrBelow100($status)
    {
        $problem = new ApiProblem($status, 'whatever');
        $model   = new RestfulJsonModel(['payload' => $problem]);
        $this->event->setModel($model);
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);

        $this->assertEquals(500, $this->response->getStatusCode());
    }
}
