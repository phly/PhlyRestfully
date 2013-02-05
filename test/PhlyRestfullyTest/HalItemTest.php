<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\HalItem;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;

class HalItemTest extends TestCase
{
    public function invalidItems()
    {
        return array(
            'null'       => array(null),
            'true'       => array(true),
            'false'      => array(false),
            'zero-int'   => array(0),
            'int'        => array(1),
            'zero-float' => array(0.0),
            'float'      => array(1.1),
            'string'     => array('string'),
        );
    }

    /**
     * @dataProvider invalidItems
     */
    public function testConstructorRaisesExceptionForNonObjectNonArrayItem($item)
    {
        $this->setExpectedException('PhlyRestfully\Exception\InvalidItemException');
        $hal = new HalItem($item, 'id', 'route');
    }

    public function testPropertiesAreAccessibleAfterConstruction()
    {
        $item = new stdClass;
        $hal  = new HalItem($item, 'id', 'route', array('foo' => 'bar'));
        $this->assertSame($item, $hal->item);
        $this->assertEquals('id', $hal->id);
        $this->assertEquals('route', $hal->route);
        $this->assertEquals(array('foo' => 'bar'), $hal->routeParams);
    }
}
