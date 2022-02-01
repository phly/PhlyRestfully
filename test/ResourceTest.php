<?php declare(strict_types=1);
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use ArrayIterator;
use PhlyRestfully\ApiProblem;
use PhlyRestfully\Exception;
use PhlyRestfully\Resource;
use PhlyRestfully\ResourceEvent;
use PhlyRestfully\ResourceInterface;
use PHPUnit\Framework\TestCase as TestCase;
use stdClass;
use Laminas\EventManager\EventManager;
use Laminas\Router\RouteMatch;
use Laminas\Stdlib\ArrayObject;
use Laminas\Stdlib\Parameters;

/**
 * @subpackage UnitTest
 */
class ResourceTest extends TestCase
{
    public function setUp(): void
    {
        $this->events   = new EventManager;
        $this->resource = new Resource();
        $this->resource->setEventManager($this->events);
    }

    public function testEventManagerIdentifiersAreAsExpected(): void
    {
        $expected = [
            Resource::class,
            ResourceInterface::class,
        ];
        $identifiers = $this->events->getIdentifiers();
        $this->assertEquals(array_values($expected), array_values($identifiers));
    }

    public function badData()
    {
        return [
            'null'   => [null],
            'bool'   => [true],
            'int'    => [1],
            'float'  => [1.0],
            'string' => ['data'],
        ];
    }

    public function badUpdateCollectionData()
    {
        return array_merge(
            $this->badData(),
            [
                'object'    => [new StdClass],
                'notnested' => [[null]],
            ]
        );
    }

    /**
     * @dataProvider badData
     */
    public function testCreateRaisesExceptionWithInvalidData($data): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->resource->create($data);
    }

    public function testEventParamsReturnDefaultValueOnNonExistingParam(): void
    {
        $this->assertEquals('world', $this->resource->getEventParam('hello', 'world'));
    }

    public function testSameInstanceReturnedByEventParams(): void
    {
        $instance = new ArrayObject();

        $this->resource->setEventParam('instance', $instance);

        $this->assertEquals($instance, $this->resource->getEventParam('instance'));
    }

    public function testClearOldParamsOnSetEventParams(): void
    {
        $this->resource->setEventParam('world', 'hello');

        $params = ['hello' => 'world'];

        $this->resource->setEventParams($params);

        $this->assertEquals($params, $this->resource->getEventParams());
    }

    public function testCreateReturnsResultOfLastListener(): void
    {
        $this->events->attach('create', function ($e): void {
            return;
        });
        $object = new stdClass;
        $this->events->attach('create', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->create([]);
        $this->assertSame($object, $test);
    }

    public function testCreateReturnsDataIfLastListenerDoesNotReturnResource(): void
    {
        $data = new stdClass;
        $object = new stdClass;
        $this->events->attach('create', function ($e) use ($object) {
            return $object;
        });
        $this->events->attach('create', function ($e): void {
            return;
        });

        $test = $this->resource->create($data);
        $this->assertSame($data, $test);
    }

    /**
     * @dataProvider badUpdateCollectionData
     */
    public function testPatchListListRaisesExceptionWithInvalidData($data): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->resource->patchList($data);
    }

    public function testPatchListReturnsResultOfLastListener(): void
    {
        $this->events->attach('patchList', function ($e): void {
            return;
        });
        $object = [new stdClass];
        $this->events->attach('patchList', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->patchList([[]]);
        $this->assertSame($object, $test);
    }

    public function testPatchListReturnsDataIfLastListenerDoesNotReturnResource(): void
    {
        $data = [new stdClass];
        $object = new stdClass;
        $this->events->attach('patchList', function ($e) use ($object) {
            return $object;
        });
        $this->events->attach('patchList', function ($e): void {
            return;
        });

        $test = $this->resource->patchList($data);
        $this->assertSame($data, $test);
    }

    /**
     * @dataProvider badData
     */
    public function testUpdateRaisesExceptionWithInvalidData($data): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->resource->update('foo', $data);
    }

    public function testUpdateReturnsResultOfLastListener(): void
    {
        $this->events->attach('update', function ($e): void {
            return;
        });
        $object = new stdClass;
        $this->events->attach('update', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->update('foo', []);
        $this->assertSame($object, $test);
    }

    public function testUpdateReturnsDataIfLastListenerDoesNotReturnResource(): void
    {
        $data = new stdClass;
        $object = new stdClass;
        $this->events->attach('update', function ($e) use ($object) {
            return $object;
        });
        $this->events->attach('update', function ($e): void {
            return;
        });

        $test = $this->resource->update('foo', $data);
        $this->assertSame($data, $test);
    }

    /**
     * @dataProvider badUpdateCollectionData
     */
    public function testReplaceListRaisesExceptionWithInvalidData($data): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->resource->replaceList($data);
    }

    public function testReplaceListReturnsResultOfLastListener(): void
    {
        $this->events->attach('replaceList', function ($e): void {
            return;
        });
        $object = [new stdClass];
        $this->events->attach('replaceList', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->replaceList([[]]);
        $this->assertSame($object, $test);
    }

    public function testReplaceListReturnsDataIfLastListenerDoesNotReturnResource(): void
    {
        $data = [new stdClass];
        $object = new stdClass;
        $this->events->attach('replaceList', function ($e) use ($object) {
            return $object;
        });
        $this->events->attach('replaceList', function ($e): void {
            return;
        });

        $test = $this->resource->replaceList($data);
        $this->assertSame($data, $test);
    }

    /**
     * @dataProvider badData
     */
    public function testPatchRaisesExceptionWithInvalidData($data): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->resource->patch('foo', $data);
    }

    public function testPatchReturnsResultOfLastListener(): void
    {
        $this->events->attach('patch', function ($e): void {
            return;
        });
        $object = new stdClass;
        $this->events->attach('patch', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->patch('foo', []);
        $this->assertSame($object, $test);
    }

    public function testPatchReturnsDataIfLastListenerDoesNotReturnResource(): void
    {
        $data = new stdClass;
        $object = new stdClass;
        $this->events->attach('patch', function ($e) use ($object) {
            return $object;
        });
        $this->events->attach('patch', function ($e): void {
            return;
        });

        $test = $this->resource->patch('foo', $data);
        $this->assertSame($data, $test);
    }

    public function testDeleteReturnsResultOfLastListenerIfBoolean(): void
    {
        $this->events->attach('delete', function ($e) {
            return new stdClass;
        });
        $this->events->attach('delete', function ($e) {
            return true;
        });

        $test = $this->resource->delete('foo', []);
        $this->assertTrue($test);
    }

    public function testDeleteReturnsFalseIfLastListenerDoesNotReturnBoolean(): void
    {
        $this->events->attach('delete', function ($e) {
            return true;
        });
        $this->events->attach('delete', function ($e) {
            return new stdClass;
        });

        $test = $this->resource->delete('foo');
        $this->assertFalse($test);
    }

    public function badDeleteCollections()
    {
        return [
            'true'     => [true],
            'int'      => [1],
            'float'    => [1.1],
            'string'   => ['string'],
            'stdClass' => [new stdClass],
        ];
    }

    /**
     * @dataProvider badDeleteCollections
     */
    public function testDeleteListRaisesInvalidArgumentExceptionForInvalidData($data): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('::deleteList');
        $this->resource->deleteList($data);
    }

    public function testDeleteListReturnsResultOfLastListenerIfBoolean(): void
    {
        $this->events->attach('deleteList', function ($e) {
            return new stdClass;
        });
        $this->events->attach('deleteList', function ($e) {
            return true;
        });

        $test = $this->resource->deleteList([]);
        $this->assertTrue($test);
    }

    public function testDeleteListReturnsFalseIfLastListenerDoesNotReturnBoolean(): void
    {
        $this->events->attach('deleteList', function ($e) {
            return true;
        });
        $this->events->attach('deleteList', function ($e) {
            return new stdClass;
        });

        $test = $this->resource->deleteList([]);
        $this->assertFalse($test);
    }

    public function testFetchReturnsResultOfLastListener(): void
    {
        $this->events->attach('fetch', function ($e) {
            return true;
        });
        $object = new stdClass;
        $this->events->attach('fetch', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->fetch('foo');
        $this->assertSame($object, $test);
    }

    /**
     * @dataProvider badData
     */
    public function testFetchReturnsFalseIfLastListenerDoesNotReturnArrayOrObject($return): void
    {
        $this->events->attach('fetch', function ($e) use ($return) {
            return $return;
        });
        $test = $this->resource->fetch('foo');
        $this->assertFalse($test);
    }

    public function invalidCollection()
    {
        return [
            'null'     => [null],
            'bool'     => [true],
            'int'      => [1],
            'float'    => [1.0],
            'string'   => ['data'],
            'stdClass' => [new stdClass],
        ];
    }

    /**
     * @dataProvider invalidCollection
     */
    public function testFetchAllReturnsEmptyArrayIfLastListenerDoesNotReturnArrayOrTraversable($return): void
    {
        $this->events->attach('fetchAll', function ($e) use ($return) {
            return $return;
        });
        $test = $this->resource->fetchAll();
        $this->assertEquals([], $test);
    }

    public function testFetchAllReturnsResultOfLastListener(): void
    {
        $this->events->attach('fetchAll', function ($e) {
            return true;
        });
        $object = new ArrayIterator([]);
        $this->events->attach('fetchAll', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->fetchAll();
        $this->assertSame($object, $test);
    }

    public function eventsToTrigger()
    {
        $id = 'resource_id';

        $resource = [
            'id'  => $id,
            'foo' => 'foo',
            'bar' => 'bar',
        ];

        $collection = [$resource];

        return [
            'create' => ['create', [$resource], false],
            'patchList' => ['patchList', [$collection], false],
            'update' => ['update', [$id, $resource], true],
            'replaceList' => ['replaceList', [$collection], false],
            'patch' => ['patch', [$id, $resource], true],
            'delete' => ['delete', [$id], true],
            'deleteList' => ['deleteList', [$collection], false],
            'fetch' => ['fetch', [$id], true],
            'fetchAll' => ['fetchAll', [], false],
        ];
    }

    /**
     * @dataProvider eventsToTrigger
     */
    public function testEventTerminateIfApiProblemIsReturned($eventName, $args, $idIsPresent): void
    {
        $called = false;

        $this->events->attach($eventName, function () {
            return new ApiProblem(400, 'Random error');
        }, 10);

        $this->events->attach($eventName, function () use (&$called): void {
            $called = true;
        }, 0);

        call_user_func_array([$this->resource, $eventName], $args);

        $this->assertFalse($called);
    }

    /**
     * @dataProvider eventsToTrigger
     */
    public function testEventParametersAreInjectedIntoEventWhenTriggered($eventName, $args, $idIsPresent): void
    {
        $test = (object) [];
        $this->events->attach($eventName, function ($e) use ($test): void {
            $test->event = $e;
        });
        $this->resource->setEventParam('id', 'OVERWRITTEN');
        $this->resource->setEventParam('parent_id', 'parent_id');

        call_user_func_array([$this->resource, $eventName], $args);

        $this->assertObjectHasAttribute('event', $test);
        $e = $test->event;

        if ($idIsPresent) {
            $this->assertTrue(false !== $e->getParam('id', false));
            $this->assertNotEquals('OVERWRITTEN', $e->getParam('id'));
        }

        $this->assertTrue(false !== $e->getParam('parent_id', false));
        $this->assertEquals('parent_id', $e->getParam('parent_id'));
    }

    /**
     * @dataProvider eventsToTrigger
     */
    public function testComposedQueryParametersAndRouteMatchesAreInjectedIntoEvent($eventName, $args): void
    {
        $test = (object) [];
        $this->events->attach($eventName, function ($e) use ($test): void {
            $test->event = $e;
        });
        $matches     = new RouteMatch([]);
        $queryParams = new Parameters();
        $this->resource->setRouteMatch($matches);
        $this->resource->setQueryParams($queryParams);

        call_user_func_array([$this->resource, $eventName], $args);

        $this->assertObjectHasAttribute('event', $test);
        $e = $test->event;

        $this->assertInstanceOf(ResourceEvent::class, $e);
        $this->assertSame($matches, $e->getRouteMatch());
        $this->assertSame($queryParams, $e->getQueryParams());
    }
}
