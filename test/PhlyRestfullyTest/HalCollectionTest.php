<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\HalCollection;
use PhlyRestfully\LinkCollection;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;

class HalCollectionTest extends TestCase
{
    public function invalidCollections()
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
            'stdclass'   => array(new stdClass),
        );
    }

    /**
     * @dataProvider invalidCollections
     */
    public function testConstructorRaisesExceptionForNonTraversableCollection($collection)
    {
        $this->setExpectedException('PhlyRestfully\Exception\InvalidCollectionException');
        $hal = new HalCollection($collection, 'collection/route', 'item/route');
    }

    public function testPropertiesAreAccessibleFollowingConstruction()
    {
        $hal = new HalCollection(array(), 'collection/route', 'item/route');
        $this->assertEquals(array(), $hal->collection);
        $this->assertEquals('collection/route', $hal->collectionRoute);
        $this->assertEquals('item/route', $hal->resourceRoute);
    }

    public function testDefaultPageIsOne()
    {
        $hal = new HalCollection(array(), 'collection/route', 'item/route');
        $this->assertEquals(1, $hal->page);
    }

    public function testPageIsMutable()
    {
        $hal = new HalCollection(array(), 'collection/route', 'item/route');
        $hal->setPage(5);
        $this->assertEquals(5, $hal->page);
    }

    public function testDefaultPageSizeIsThirty()
    {
        $hal = new HalCollection(array(), 'collection/route', 'item/route');
        $this->assertEquals(30, $hal->pageSize);
    }

    public function testPageSizeIsMutable()
    {
        $hal = new HalCollection(array(), 'collection/route', 'item/route');
        $hal->setPageSize(3);
        $this->assertEquals(3, $hal->pageSize);
    }

    public function testDefaultCollectionNameIsItems()
    {
        $hal = new HalCollection(array(), 'collection/route', 'item/route');
        $this->assertEquals('items', $hal->collectionName);
    }

    public function testCollectionNameIsMutable()
    {
        $hal = new HalCollection(array(), 'collection/route', 'item/route');
        $hal->setCollectionName('records');
        $this->assertEquals('records', $hal->collectionName);
    }

    public function testDefaultAttributesAreEmpty()
    {
        $hal = new HalCollection(array(), 'collection/route', 'item/route');
        $this->assertEquals(array(), $hal->attributes);
    }

    public function testAttributesAreMutable()
    {
        $hal = new HalCollection(array(), 'collection/route', 'item/route');
        $attributes = array(
            'count' => 1376,
            'order' => 'desc',
        );
        $hal->setAttributes($attributes);
        $this->assertEquals($attributes, $hal->attributes);
    }

    public function testComposesLinkCollectionByDefault()
    {
        $hal = new HalCollection(array(), 'collection/route', 'item/route');
        $this->assertInstanceOf('PhlyRestfully\LinkCollection', $hal->getLinks());
    }

    public function testLinkCollectionMayBeInjected()
    {
        $hal   = new HalCollection(array(), 'collection/route', 'item/route');
        $links = new LinkCollection();
        $hal->setLinks($links);
        $this->assertSame($links, $hal->getLinks());
    }
}
