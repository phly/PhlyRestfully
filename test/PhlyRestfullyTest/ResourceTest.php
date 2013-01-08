<?php

namespace PhlyRestfullyTest;

use ArrayIterator;
use PhlyRestfully\Resource;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;
use Zend\EventManager\EventManager;

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

    public function testCreateReturnsDataIfLastListenerDoesNotReturnItem()
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

    public function testUpdateReturnsDataIfLastListenerDoesNotReturnItem()
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

    public function testPatchReturnsDataIfLastListenerDoesNotReturnItem()
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
}
