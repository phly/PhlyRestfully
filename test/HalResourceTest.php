<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use PhlyRestfully\Exception;
use PhlyRestfully\HalResource;
use PhlyRestfully\LinkCollection;
use PHPUnit\Framework\TestCase as TestCase;
use stdClass;

class HalResourceTest extends TestCase
{
    public function invalidResources()
    {
        return [
            'null'       => [null],
            'true'       => [true],
            'false'      => [false],
            'zero-int'   => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'string'     => ['string'],
        ];
    }

    /**
     * @dataProvider invalidResources
     */
    public function testConstructorRaisesExceptionForNonObjectNonArrayResource($resource)
    {
        $this->expectException(Exception\InvalidResourceException::class);
        $hal = new HalResource($resource, 'id');
    }

    public function testPropertiesAreAccessibleAfterConstruction()
    {
        $resource = new stdClass;
        $hal      = new HalResource($resource, 'id');
        $this->assertSame($resource, $hal->resource);
        $this->assertEquals('id', $hal->id);
    }

    public function testComposesLinkCollectionByDefault()
    {
        $resource = new stdClass;
        $hal      = new HalResource($resource, 'id', 'route', ['foo' => 'bar']);
        $this->assertInstanceOf(LinkCollection::class, $hal->getLinks());
    }

    public function testLinkCollectionMayBeInjected()
    {
        $resource = new stdClass;
        $hal      = new HalResource($resource, 'id', 'route', ['foo' => 'bar']);
        $links    = new LinkCollection();
        $hal->setLinks($links);
        $this->assertSame($links, $hal->getLinks());
    }
}
