<?php

namespace PhlyRestfullyTest;

use PhlyRestfully\RestfulJsonModel;
use PhlyRestfully\RestfulJsonStrategy;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Http\Response;
use Zend\View\Renderer\JsonRenderer;
use Zend\View\ViewEvent;

class RestfulJsonStrategyTest extends TestCase
{
    public function setUp()
    {
        $this->response = new Response;
        $this->event    = new ViewEvent;
        $this->event->setResponse($this->response);

        $this->renderer = new JsonRenderer;
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
        $this->event->setResult(array('foo'));
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
        $model = new RestfulJsonModel(array(
            'describedBy' => 'foo',
            'title' => 'bar',
        ));
        $this->event->setModel($model);
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/api-problem+json', $header->getFieldValue());
    }

    public function testInjectResponseSetsContentTypeHeaderToHalForHalModel()
    {
        $model = new RestfulJsonModel(array(
            '_links' => array('foo' => 'bar'),
        ));
        $this->event->setModel($model);
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/hal+json', $header->getFieldValue());
    }
}
