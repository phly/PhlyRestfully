<?php

namespace PhlyRestfullyTest\Plugin;

use PhlyRestfully\Plugin\ApiProblemResult;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Http\Response;

class ApiProblemResultTest extends TestCase
{
    public function setUp()
    {
        $this->response   = new Response;
        $this->controller = $this->getMock('PhlyRestfully\ResourceController');
        $this->controller->expects($this->any())
                         ->method('getResponse')
                         ->will($this->returnValue($this->response));
        $this->plugin = new ApiProblemResult();
        $this->plugin->setController($this->controller);
    }

    public function problemApis()
    {
        return array(
            '404-status-detail' => array(array(404, 'foo'), array('describedBy' => 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html', 'title' => 'Not Found', 'httpStatus' => 404, 'detail' => 'foo')),
            '409-status-detail' => array(array(409, 'foo'), array('describedBy' => 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html', 'title' => 'Conflict', 'httpStatus' => 409, 'detail' => 'foo')),
            '422-status-detail' => array(array(422, 'foo'), array('describedBy' => 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html', 'title' => 'Unprocessable Entity', 'httpStatus' => 422, 'detail' => 'foo')),
            '500-status-detail' => array(array(500, 'foo'), array('describedBy' => 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html', 'title' => 'Internal Server Error', 'httpStatus' => 500, 'detail' => 'foo')),
            'unknown-status-detail' => array(array(416, 'foo'), array('describedBy' => 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html', 'title' => 'Unknown', 'httpStatus' => 416, 'detail' => 'foo')),
            'status-detail-describedby' => array(array(416, 'foo', 'foo'), array('describedBy' => 'foo', 'title' => 'Unknown', 'httpStatus' => 416, 'detail' => 'foo')),
            'status-detail-describedby-title' => array(array(416, 'foo', 'foo', 'foo'), array('describedBy' => 'foo', 'title' => 'foo', 'httpStatus' => 416, 'detail' => 'foo')),
        );
    }

    /**
     * @dataProvider problemApis
     */
    public function testGenerateProducesExpectedPayload($args, $expected)
    {
        $httpStatus = $args[0];
        $result = call_user_func_array(array($this->plugin, 'generate'), $args);
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider problemApis
     */
    public function testInvokeProxiesToGenerate($args, $expected)
    {
        $httpStatus = $args[0];
        $result = call_user_func_array(array($this->plugin, '__invoke'), $args);
        $this->assertEquals($expected, $result);
    }
}
