<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\ApiProblem;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;

class ApiProblemTest extends TestCase
{
    public function httpStatusCodes()
    {
        return array(
            '200' => array(200),
            '201' => array(201),
            '300' => array(300),
            '301' => array(301),
            '302' => array(302),
            '400' => array(400),
            '401' => array(401),
            '404' => array(404),
            '500' => array(500),
        );
    }

    /**
     * @dataProvider httpStatusCodes
     */
    public function testHttpStatusIsUsedVerbatim($status)
    {
        $apiProblem = new ApiProblem($status, 'foo');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('httpStatus', $payload);
        $this->assertEquals($status, $payload['httpStatus']);
    }

    public function testExceptionCodeIsUsedForHttpStatus()
    {
        $exception  = new \Exception('exception message', 401);
        $apiProblem = new ApiProblem('500', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('httpStatus', $payload);
        $this->assertEquals($exception->getCode(), $payload['httpStatus']);
    }

    public function testDetailStringIsUsedVerbatim()
    {
        $apiProblem = new ApiProblem('500', 'foo');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('detail', $payload);
        $this->assertEquals('foo', $payload['detail']);
    }

    public function testExceptionMessageIsUsedForDetail()
    {
        $exception  = new \Exception('exception message');
        $apiProblem = new ApiProblem('500', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('detail', $payload);
        $this->assertEquals($exception->getMessage(), $payload['detail']);
    }

    public function testDetailCanIncludeStackTrace()
    {
        $exception  = new \Exception('exception message');
        $apiProblem = new ApiProblem('500', $exception);
        $apiProblem->setDetailIncludesStackTrace(true);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('detail', $payload);
        $this->assertEquals($exception->getMessage() . "\n" . $exception->getTraceAsString(), $payload['detail']);
    }

    public function testDetailCanIncludeNestedExceptions()
    {
        $exceptionChild  = new \Exception('child exception');
        $exceptionParent = new \Exception('parent exception', null, $exceptionChild);

        $apiProblem = new ApiProblem('500', $exceptionParent);
        $apiProblem->setDetailIncludesStackTrace(true);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('detail', $payload);
        $expected = $exceptionParent->getMessage() . "\n"
            . $exceptionParent->getTraceAsString() . "\n"
            . $exceptionChild->getMessage() . "\n"
            . $exceptionChild->getTraceAsString();
        $this->assertEquals($expected, $payload['detail']);
    }

    public function testDescribedByUrlIsUsedVerbatim()
    {
        $apiProblem = new ApiProblem('500', 'foo', 'http://status.dev:8080/details.md');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('describedBy', $payload);
        $this->assertEquals('http://status.dev:8080/details.md', $payload['describedBy']);
    }

    public function knownHttpStatusCodes()
    {
        return array(
            '404' => array(404),
            '409' => array(409),
            '422' => array(422),
            '500' => array(500),
        );
    }

    /**
     * @dataProvider knownHttpStatusCodes
     */
    public function testKnownHttpStatusResultsInKnownTitle($httpStatus)
    {
        $apiProblem = new ApiProblem($httpStatus, 'foo');
        $r = new ReflectionObject($apiProblem);
        $p = $r->getProperty('problemStatusTitles');
        $p->setAccessible(true);
        $titles = $p->getValue($apiProblem);

        $payload = $apiProblem->toArray();
        $this->assertArrayHasKey('title', $payload);
        $this->assertEquals($titles[$httpStatus], $payload['title']);
    }

    public function testUnknownHttpStatusResultsInUnknownTitle()
    {
        $apiProblem = new ApiProblem(420, 'foo');
        $payload = $apiProblem->toArray();
        $this->assertArrayHasKey('title', $payload);
        $this->assertEquals('Unknown', $payload['title']);
    }

    public function testProvidedTitleIsUsedVerbatim()
    {
        $apiProblem = new ApiProblem('500', 'foo', 'http://status.dev:8080/details.md', 'some title');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('title', $payload);
        $this->assertEquals('some title', $payload['title']);
    }
}
