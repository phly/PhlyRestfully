<?php

namespace PhlyRestfullyTest;

use PhlyRestfully\Resource;
use PHPUnit_Framework_TestCase as TestCase;
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
}
