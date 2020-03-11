<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\ApiProblem;
use PhlyRestfully\Exception;
use PHPUnit\Framework\TestCase as TestCase;
use ReflectionObject;

class ApiProblemTest extends TestCase
{
    public function httpStatusCodes()
    {
        return [
            '200' => [200],
            '201' => [201],
            '300' => [300],
            '301' => [301],
            '302' => [302],
            '400' => [400],
            '401' => [401],
            '404' => [404],
            '500' => [500],
        ];
    }

    /**
     * @dataProvider httpStatusCodes
     */
    public function testHttpStatusIsUsedVerbatim($status): void
    {
        $apiProblem = new ApiProblem($status, 'foo');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('httpStatus', $payload);
        $this->assertEquals($status, $payload['httpStatus']);
    }

    public function testExceptionCodeIsUsedForHttpStatus(): void
    {
        $exception  = new \Exception('exception message', 401);
        $apiProblem = new ApiProblem('500', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('httpStatus', $payload);
        $this->assertEquals($exception->getCode(), $payload['httpStatus']);
    }

    public function testDetailStringIsUsedVerbatim(): void
    {
        $apiProblem = new ApiProblem('500', 'foo');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('detail', $payload);
        $this->assertEquals('foo', $payload['detail']);
    }

    public function testExceptionMessageIsUsedForDetail(): void
    {
        $exception  = new \Exception('exception message');
        $apiProblem = new ApiProblem('500', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('detail', $payload);
        $this->assertEquals($exception->getMessage(), $payload['detail']);
    }

    public function testDetailCanIncludeStackTrace(): void
    {
        $exception  = new \Exception('exception message');
        $apiProblem = new ApiProblem('500', $exception);
        $apiProblem->setDetailIncludesStackTrace(true);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('detail', $payload);
        $this->assertEquals($exception->getMessage() . "\n" . $exception->getTraceAsString(), $payload['detail']);
    }

    public function testDetailCanIncludeNestedExceptions(): void
    {
        $exceptionChild  = new \Exception('child exception');
        $exceptionParent = new \Exception('parent exception', 0, $exceptionChild);

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

    public function testDescribedByUrlIsUsedVerbatim(): void
    {
        $apiProblem = new ApiProblem('500', 'foo', 'http://status.dev:8080/details.md');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('describedBy', $payload);
        $this->assertEquals('http://status.dev:8080/details.md', $payload['describedBy']);
    }

    public function knownHttpStatusCodes()
    {
        return [
            '404' => [404],
            '409' => [409],
            '422' => [422],
            '500' => [500],
        ];
    }

    /**
     * @dataProvider knownHttpStatusCodes
     */
    public function testKnownHttpStatusResultsInKnownTitle($httpStatus): void
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

    public function testUnknownHttpStatusResultsInUnknownTitle(): void
    {
        $apiProblem = new ApiProblem(420, 'foo');
        $payload = $apiProblem->toArray();
        $this->assertArrayHasKey('title', $payload);
        $this->assertEquals('Unknown', $payload['title']);
    }

    public function testProvidedTitleIsUsedVerbatim(): void
    {
        $apiProblem = new ApiProblem('500', 'foo', 'http://status.dev:8080/details.md', 'some title');
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('title', $payload);
        $this->assertEquals('some title', $payload['title']);
    }

    public function testCanPassArbitraryDetailsToConstructor(): void
    {
        $problem = new ApiProblem(
            400,
            'Invalid input',
            'http://example.com/api/problem/400',
            'Invalid entity',
            ['foo' => 'bar']
        );
        $this->assertEquals('bar', $problem->foo);
    }

    public function testArraySerializationIncludesArbitraryDetails(): void
    {
        $problem = new ApiProblem(
            400,
            'Invalid input',
            'http://example.com/api/problem/400',
            'Invalid entity',
            ['foo' => 'bar']
        );
        $array   = $problem->toArray();
        $this->assertArrayHasKey('foo', $array);
        $this->assertEquals('bar', $array['foo']);
    }

    public function testArbitraryDetailsShouldNotOverwriteRequiredFieldsInArraySerialization(): void
    {
        $problem = new ApiProblem(
            400,
            'Invalid input',
            'http://example.com/api/problem/400',
            'Invalid entity',
            ['title' => 'SHOULD NOT GET THIS']
        );
        $array   = $problem->toArray();
        $this->assertArrayHasKey('title', $array);
        $this->assertEquals('Invalid entity', $array['title']);
    }

    public function testUsesTitleFromExceptionWhenProvided(): void
    {
        $exception  = new Exception\CreationException('exception message', 401);
        $exception->setTitle('problem title');
        $apiProblem = new ApiProblem('401', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('title', $payload);
        $this->assertEquals($exception->getTitle(), $payload['title']);
    }

    public function testUsesDescribedByFromExceptionWhenProvided(): void
    {
        $exception  = new Exception\CreationException('exception message', 401);
        $exception->setDescribedBy('http://example.com/api/help/401');
        $apiProblem = new ApiProblem('401', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('describedBy', $payload);
        $this->assertEquals($exception->getDescribedBy(), $payload['describedBy']);
    }

    public function testUsesAdditionalDetailsFromExceptionWhenProvided(): void
    {
        $exception  = new Exception\CreationException('exception message', 401);
        $exception->setAdditionalDetails(['foo' => 'bar']);
        $apiProblem = new ApiProblem('401', $exception);
        $payload    = $apiProblem->toArray();
        $this->assertArrayHasKey('foo', $payload);
        $this->assertEquals('bar', $payload['foo']);
    }

    public function testGetExceptionReturnsExceptionIfPassedInAsDetail(): void
    {
        $exception  = new Exception\CreationException('exception message', 401);
        $apiProblem = new ApiProblem('401', $exception);

        $this->assertTrue($apiProblem->getException() instanceof Exception\CreationException);
    }

    public function testGetExceptionReturnsNullIfStringPassedInAsDetail(): void
    {
        $apiProblem = new ApiProblem('401', 'Bad error happened');

        $this->assertNull($apiProblem->getException());
    }
}
