<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest;

use ArrayObject;
use PhlyRestfully\RestfulJsonModel;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Stdlib\Hydrator;

/**
 * @subpackage UnitTest
 */
class RestfulJsonModelTest extends TestCase
{
    public function setUp()
    {
        $this->model = new RestfulJsonModel;
    }

    public function testCanAddHydratorsForClassNames()
    {
        $this->assertFalse($this->model->hasHydrator($this));

        $hydrator = new Hydrator\ArraySerializable;
        $this->model->addHydrator(get_class($this), $hydrator);
        $this->assertTrue($this->model->hasHydrator($this));
        $test = $this->model->getHydrator($this);
        $this->assertSame($hydrator, $test);
    }

    public function testRaisesExceptionRetrievingHydratorWhenNotFound()
    {
        $this->setExpectedException('PhlyRestfully\Exception\RuntimeException');
        $this->model->getHydrator($this);
    }

    public function testCanSetDefaultHydrator()
    {
        $hydrator = new Hydrator\ArraySerializable;
        $this->model->setDefaultHydrator($hydrator);
        $this->assertAttributeInstanceOf(get_class($hydrator), 'defaultHydrator', $this->model);
    }

    public function invalidProblemApiVariables()
    {
        return array(
            'empty' => array(array()),
            'only-described' => array(array('describedby' => 'foo')),
            'only-title' => array(array('title' => 'foo')),
        );
    }

    /**
     * @dataProvider invalidProblemApiVariables
     */
    public function testIsProblemApiReturnsFalseIfBothDescribedbyAndTitleAreNotPresent($variables)
    {
        $this->model->setVariables($variables);
        $this->assertFalse($this->model->isProblemApi());
    }

    public function validProblemApiVariables()
    {
        return array(
            'minimal' => array(array('describedBy' => 'foo', 'title' => 'foo')),
            'with-http-status' => array(array('describedBy' => 'foo', 'title' => 'foo', 'httpStatus' => 500)),
            'with-detail' => array(array('describedBy' => 'foo', 'title' => 'foo', 'detail' => 'foo')),
            'full' => array(array('describedBy' => 'foo', 'title' => 'foo', 'detail' => 'foo', 'httpStatus' => 500)),
            'full-and-some' => array(array('describedBy' => 'foo', 'title' => 'foo', 'detail' => 'foo', 'httpStatus' => 500, 'something-extra' => 'foo')),
        );
    }

    /**
     * @dataProvider validProblemApiVariables
     */
    public function testIsProblemApiReturnsTrueWhenBothDescribedbyAndTitleArePresent($variables)
    {
        $this->model->setVariables($variables);
        $this->assertTrue($this->model->isProblemApi());
    }

    /**
     * @dataProvider validProblemApiVariables
     */
    public function testSerializeProblemApiOnlySerializesMembersExpected($variables)
    {
        $this->model->setVariables($variables);
        $serialized = $this->model->serializeProblemApi();
        $test       = json_decode($serialized, true);

        $base = array(
            'describedBy' => '',
            'title'       => '',
            'httpStatus'  => 500,
            'detail'      => '',
        );
        $intersection = array_intersect_assoc($base, $test);
        $this->assertLessThanOrEqual(count(array_keys($base)), count(array_keys($intersection)));
    }

    public function invalidHalVariables()
    {
        return array(
            'empty'   => array(array()),
            'minimal' => array(array('foo' => 'bar')),
            'object'  => array(new ArrayObject(array('foo' => 'bar'))),
        );
    }

    /**
     * @dataProvider invalidHalVariables
     */
    public function testIsHalReturnsFalseIfNoLinksMemberPresent($variables)
    {
        $this->model->setVariables($variables);
        $this->assertFalse($this->model->isHal());
    }

    public function validHalVariables()
    {
        return array(
            'only-links' => array(array('_links' => array())),
            'links-and-some' => array(array('_links' => array(), 'foo' => 'bar')),
        );
    }

    /**
     * @dataProvider validHalVariables
     */
    public function testIsHalReturnsTrueIfLinksMemberPresent($variables)
    {
        $this->model->setVariables($variables);
        $this->assertTrue($this->model->isHal());
    }

    public function testSerializeItemReturnsItemVerbatimIfJsonSerializable()
    {
        if (version_compare(PHP_VERSION, '5.4.0', 'lt')) {
            $this->markTestSkipped('Need PHP 5.4.0 or greater to test JsonSerializable capabilities');
        }

        $item = new TestAsset\JsonSerializable;
        $test = $this->model->serializeItem($item);
        $this->assertSame($item, $test);
    }

    public function testCastsItemToArrayIfNoHydratorOrDefaultHydratorPresent()
    {
        $array = array('foo' => 'bar');
        $item  = (object) $array;
        $test  = $this->model->serializeItem($item);
        $this->assertInternalType('array', $test);
        $this->assertEquals($array, $test);
    }

    public function testSerializeItemUsesDefaultHydratorWhenSetAndNoHydratorPresentForItem()
    {
        $this->model->setDefaultHydrator(new Hydrator\ArraySerializable());
        $item  = new TestAsset\ArraySerializable();
        $this->assertFalse($this->model->hasHydrator($item));

        $test  = $this->model->serializeItem($item);
        $this->assertInternalType('array', $test);
        $this->assertEquals($item->getArrayCopy(), $test);
    }

    public function testSerializeItemUsesMappedHydratorWhenAvailable()
    {
        $this->model->setDefaultHydrator(new Hydrator\ClassMethods());
        $this->model->addHydrator(__NAMESPACE__ . '\TestAsset\ArraySerializable', new Hydrator\ArraySerializable());
        $item  = new TestAsset\ArraySerializable();
        $this->assertTrue($this->model->hasHydrator($item));

        $test  = $this->model->serializeItem($item);
        $this->assertInternalType('array', $test);
        $this->assertEquals($item->getArrayCopy(), $test);
    }

    public function testSerializeItemsReturnsArrayOfSerializedItems()
    {
        $this->model->setDefaultHydrator(new Hydrator\ClassMethods());
        $this->model->addHydrator(__NAMESPACE__ . '\TestAsset\ArraySerializable', new Hydrator\ArraySerializable);

        $items = array(
            array('item' => array('foo' => 'bar')),
            array('item' => (object) array('foo' => 'bar')),
            array('item' => new TestAsset\ArraySerializable()),
            array('item' => new TestAsset\ClassMethods()),
        );
        $expected = array(
            array('item' => array('foo' => 'bar')),
            array('item' => array()),   // no class methods
            array('item' => array('foo' => 'bar')),
            array('item' => array('foo' => 'bar')),
        );
        if (version_compare(PHP_VERSION, '5.4.0', 'gte')) {
            $jsonItem   = new TestAsset\JsonSerializable();
            $items[]    = array('item' => $jsonItem);
            $expected[] = array('item' => $jsonItem);
        }
        $test = $this->model->serializeItems($items);
        $this->assertEquals($expected, $test);
    }

    /**
     * @dataProvider validProblemApiVariables
     */
    public function testSerializeReturnsProblemApiJsonIfModelIsProblemApi($variables)
    {
        $this->model->setVariables($variables);
        $json = $this->model->serialize();
        $test = json_decode($json, true);

        $base = array(
            'describedBy' => '',
            'title'       => '',
            'httpStatus'  => 500,
            'detail'      => '',
        );
        $intersection = array_intersect_assoc($base, $test);
        $this->assertLessThanOrEqual(count(array_keys($base)), count(array_keys($intersection)));
    }

    public function itemToSerialize()
    {
        $items = array(
            'array' => array(array('foo' => 'bar'), array('foo' => 'bar')),
            'bare-object' => array((object) array('foo' => 'bar'), array()),
            'with-hydrator' => array(new TestAsset\ArraySerializable(), array('foo' => 'bar')),
            'default-hydrator' => array(new TestAsset\ClassMethods(), array('foo' => 'bar')),
        );
        if (version_compare(PHP_VERSION, '5.4.0', 'gte')) {
            $items['json-serializable'] = array(new TestAsset\JsonSerializable, array('foo' => 'bar'));
        }
        return $items;
    }

    /**
     * @dataProvider itemToSerialize
     */
    public function testSerializeReturnsSerializedItemWhenItemKeyPresent($item, $expected)
    {
        $this->model->setDefaultHydrator(new Hydrator\ClassMethods());
        $this->model->addHydrator(__NAMESPACE__ . '\TestAsset\ArraySerializable', new Hydrator\ArraySerializable);

        $this->model->setVariable('item', $item);
        $json = $this->model->serialize();
        $data = json_decode($json, true);
        $this->assertArrayHasKey('item', $data);
        $this->assertEquals($expected, $data['item']);
    }

    public function itemsToSerialize()
    {
        $items = array(
            array('item' => array('foo' => 'bar')),
            array('item' => (object) array('foo' => 'bar')),
            array('item' => new TestAsset\ArraySerializable()),
            array('item' => new TestAsset\ClassMethods()),
        );
        $expected = array(
            array('item' => array('foo' => 'bar')),
            array('item' => array()),               // no class methods
            array('item' => array('foo' => 'bar')),
            array('item' => array('foo' => 'bar')),
        );
        if (version_compare(PHP_VERSION, '5.4.0', 'gte')) {
            $jsonItem   = new TestAsset\JsonSerializable();
            $items[]    = array('item' => $jsonItem);
            $expected[] = array('item' => array('foo' => 'bar'));
        }
        return array(
            'array' => array($items, $expected),
        );
    }

    /**
     * @dataProvider itemsToSerialize
     */
    public function testSerializeReturnsSerializedItemsWhenItemsKeyPresent($items, $expected)
    {
        $this->model->setDefaultHydrator(new Hydrator\ClassMethods());
        $this->model->addHydrator(__NAMESPACE__ . '\TestAsset\ArraySerializable', new Hydrator\ArraySerializable);

        $this->model->setVariable('items', $items);
        $json = $this->model->serialize();
        $data = json_decode($json, true);
        $this->assertArrayHasKey('items', $data);
        $this->assertEquals($expected, $data['items']);
    }
}
