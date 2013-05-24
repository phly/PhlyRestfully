<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use ArrayIterator;
use PhlyRestfully\ApiProblem;
use PhlyRestfully\Resource;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;
use Zend\EventManager\EventManager;
use Zend\Mvc\Router\RouteMatch;
use Zend\Stdlib\ArrayObject;
use Zend\Stdlib\Parameters;

/**
 * @subpackage UnitTest
 */
class ResourceTest extends TestCase
{
    public function setUp()
    {
        $this->events   = new EventManager;
        $this->resource = new Resource();
        $this->resource->setEventManager($this->events);
    }

    public function testEventManagerIdentifiersAreAsExpected()
    {
        $expected = array(
            'PhlyRestfully\Resource',
            'PhlyRestfully\ResourceInterface',
        );
        $identifiers = $this->events->getIdentifiers();
        $this->assertEquals(array_values($expected), array_values($identifiers));
    }

    public function badData()
    {
        return array(
            'null'   => array(null),
            'bool'   => array(true),
            'int'    => array(1),
            'float'  => array(1.0),
            'string' => array('data'),
        );
    }

    /**
     * @dataProvider badData
     */
    public function testCreateRaisesExceptionWithInvalidData($data)
    {
        $this->setExpectedException('PhlyRestfully\Exception\InvalidArgumentException');
        $this->resource->create($data);
    }

    public function testEventParamsReturnDefaultValueOnNonExistingParam()
    {
        $this->assertEquals('world', $this->resource->getEventParam('hello', 'world'));
    }

    public function testSameInstanceReturnedByEventParams()
    {
        $instance = new ArrayObject();

        $this->resource->setEventParam('instance', $instance);

        $this->assertEquals($instance, $this->resource->getEventParam('instance'));
    }

    public function testClearOldParamsOnSetEventParams()
    {
        $this->resource->setEventParam('world', 'hello');

        $params = array('hello' => 'world');

        $this->resource->setEventParams($params);

        $this->assertEquals($params, $this->resource->getEventParams());
    }

    public function testCreateReturnsResultOfLastListener()
    {
        $this->events->attach('create', function ($e) {
            return;
        });
        $object = new stdClass;
        $this->events->attach('create', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->create(array());
        $this->assertSame($object, $test);
    }

    public function testCreateReturnsDataIfLastListenerDoesNotReturnResource()
    {
        $data = new stdClass;
        $object = new stdClass;
        $this->events->attach('create', function ($e) use ($object) {
            return $object;
        });
        $this->events->attach('create', function ($e) {
            return;
        });

        $test = $this->resource->create($data);
        $this->assertSame($data, $test);
    }

    /**
     * @dataProvider badData
     */
    public function testUpdateRaisesExceptionWithInvalidData($data)
    {
        $this->setExpectedException('PhlyRestfully\Exception\InvalidArgumentException');
        $this->resource->update('foo', $data);
    }

    public function testUpdateReturnsResultOfLastListener()
    {
        $this->events->attach('update', function ($e) {
            return;
        });
        $object = new stdClass;
        $this->events->attach('update', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->update('foo', array());
        $this->assertSame($object, $test);
    }

    public function testUpdateReturnsDataIfLastListenerDoesNotReturnResource()
    {
        $data = new stdClass;
        $object = new stdClass;
        $this->events->attach('update', function ($e) use ($object) {
            return $object;
        });
        $this->events->attach('update', function ($e) {
            return;
        });

        $test = $this->resource->update('foo', $data);
        $this->assertSame($data, $test);
    }

    /**
     * @dataProvider badData
     */
    public function testReplaceListRaisesExceptionWithInvalidData($data)
    {
        $this->setExpectedException('PhlyRestfully\Exception\InvalidArgumentException');
        $this->resource->replaceList($data);
    }

    public function testReplaceListReturnsResultOfLastListener()
    {
        $this->events->attach('replaceList', function ($e) {
            return;
        });
        $object = array(new stdClass);
        $this->events->attach('replaceList', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->replaceList(array(array()));
        $this->assertSame($object, $test);
    }

    public function testReplaceListReturnsDataIfLastListenerDoesNotReturnResource()
    {
        $data = array(new stdClass);
        $object = new stdClass;
        $this->events->attach('replaceList', function ($e) use ($object) {
            return $object;
        });
        $this->events->attach('replaceList', function ($e) {
            return;
        });

        $test = $this->resource->replaceList($data);
        $this->assertSame($data, $test);
    }

    /**
     * @dataProvider badData
     */
    public function testPatchRaisesExceptionWithInvalidData($data)
    {
        $this->setExpectedException('PhlyRestfully\Exception\InvalidArgumentException');
        $this->resource->patch('foo', $data);
    }

    public function testPatchReturnsResultOfLastListener()
    {
        $this->events->attach('patch', function ($e) {
            return;
        });
        $object = new stdClass;
        $this->events->attach('patch', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->patch('foo', array());
        $this->assertSame($object, $test);
    }

    public function testPatchReturnsDataIfLastListenerDoesNotReturnResource()
    {
        $data = new stdClass;
        $object = new stdClass;
        $this->events->attach('patch', function ($e) use ($object) {
            return $object;
        });
        $this->events->attach('patch', function ($e) {
            return;
        });

        $test = $this->resource->patch('foo', $data);
        $this->assertSame($data, $test);
    }

    public function testDeleteReturnsResultOfLastListenerIfBoolean()
    {
        $this->events->attach('delete', function ($e) {
            return new stdClass;
        });
        $this->events->attach('delete', function ($e) {
            return true;
        });

        $test = $this->resource->delete('foo', array());
        $this->assertTrue($test);
    }

    public function testDeleteReturnsFalseIfLastListenerDoesNotReturnBoolean()
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
        return array(
            'true'     => array(true),
            'int'      => array(1),
            'float'    => array(1.1),
            'string'   => array('string'),
            'stdClass' => array(new stdClass),
        );
    }

    /**
     * @dataProvider badDeleteCollections
     */
    public function testDeleteListRaisesInvalidArgumentExceptionForInvalidData($data)
    {
        $this->setExpectedException('PhlyRestfully\Exception\InvalidArgumentException', '::deleteList');
        $this->resource->deleteList($data);
    }

    public function testDeleteListReturnsResultOfLastListenerIfBoolean()
    {
        $this->events->attach('deleteList', function ($e) {
            return new stdClass;
        });
        $this->events->attach('deleteList', function ($e) {
            return true;
        });

        $test = $this->resource->deleteList(array());
        $this->assertTrue($test);
    }

    public function testDeleteListReturnsFalseIfLastListenerDoesNotReturnBoolean()
    {
        $this->events->attach('deleteList', function ($e) {
            return true;
        });
        $this->events->attach('deleteList', function ($e) {
            return new stdClass;
        });

        $test = $this->resource->deleteList(array());
        $this->assertFalse($test);
    }

    public function testFetchReturnsResultOfLastListener()
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
    public function testFetchReturnsFalseIfLastListenerDoesNotReturnArrayOrObject($return)
    {
        $this->events->attach('fetch', function ($e) use ($return) {
            return $return;
        });
        $test = $this->resource->fetch('foo');
        $this->assertFalse($test);
    }

    public function invalidCollection()
    {
        return array(
            'null'     => array(null),
            'bool'     => array(true),
            'int'      => array(1),
            'float'    => array(1.0),
            'string'   => array('data'),
            'stdClass' => array(new stdClass),
        );
    }

    /**
     * @dataProvider invalidCollection
     */
    public function testFetchAllReturnsEmptyArrayIfLastListenerDoesNotReturnArrayOrTraversable($return)
    {
        $this->events->attach('fetchAll', function ($e) use ($return) {
            return $return;
        });
        $test = $this->resource->fetchAll();
        $this->assertEquals(array(), $test);
    }

    public function testFetchAllReturnsResultOfLastListener()
    {
        $this->events->attach('fetchAll', function ($e) {
            return true;
        });
        $object = new ArrayIterator(array());
        $this->events->attach('fetchAll', function ($e) use ($object) {
            return $object;
        });

        $test = $this->resource->fetchAll();
        $this->assertSame($object, $test);
    }

    public function eventsToTrigger()
    {
        $id = 'resource_id';

        $resource = array(
            'id'  => $id,
            'foo' => 'foo',
            'bar' => 'bar',
        );

        $collection = array($resource);

        return array(
            'create' => array('create', array($resource), false),
            'update' => array('update', array($id, $resource), true),
            'replaceList' => array('replaceList', array($collection), false),
            'patch' => array('patch', array($id, $resource), true),
            'delete' => array('delete', array($id), true),
            'deleteList' => array('deleteList', array($collection), false),
            'fetch' => array('fetch', array($id), true),
            'fetchAll' => array('fetchAll', array(), false),
        );
    }

    /**
     * @dataProvider eventsToTrigger
     */
    public function testEventTerminateIfApiProblemIsReturned($eventName, $args, $idIsPresent)
    {
        $called = false;

        $this->events->attach($eventName, function() {
            return new ApiProblem(400, 'Random error');
        }, 10);

        $this->events->attach($eventName, function() use (&$called) {
            $called = true;
        }, 0);

        call_user_func_array(array($this->resource, $eventName), $args);

        $this->assertFalse($called);
    }

    /**
     * @dataProvider eventsToTrigger
     */
    public function testEventParametersAreInjectedIntoEventWhenTriggered($eventName, $args, $idIsPresent)
    {
        $test = (object) array();
        $this->events->attach($eventName, function ($e) use ($test) {
            $test->event = $e;
        });
        $this->resource->setEventParam('id', 'OVERWRITTEN');
        $this->resource->setEventParam('parent_id', 'parent_id');

        call_user_func_array(array($this->resource, $eventName), $args);

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
    public function testComposedQueryParametersAndRouteMatchesAreInjectedIntoEvent($eventName, $args)
    {
        $test = (object) array();
        $this->events->attach($eventName, function ($e) use ($test) {
            $test->event = $e;
        });
        $matches     = new RouteMatch(array());
        $queryParams = new Parameters();
        $this->resource->setRouteMatch($matches);
        $this->resource->setQueryParams($queryParams);

        call_user_func_array(array($this->resource, $eventName), $args);

        $this->assertObjectHasAttribute('event', $test);
        $e = $test->event;

        $this->assertInstanceOf('PhlyRestfully\ResourceEvent', $e);
        $this->assertSame($matches, $e->getRouteMatch());
        $this->assertSame($queryParams, $e->getQueryParams());
    }
}
