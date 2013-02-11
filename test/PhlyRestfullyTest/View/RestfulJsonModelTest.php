<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\View;

use PhlyRestfully\ApiProblem;
use PhlyRestfully\HalCollection;
use PhlyRestfully\HalResource;
use PhlyRestfully\View\RestfulJsonModel;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;

/**
 * @subpackage UnitTest
 */
class RestfulJsonModelTest extends TestCase
{
    public function setUp()
    {
        $this->model = new RestfulJsonModel;
    }

    public function testPayloadIsNullByDefault()
    {
        $this->assertNull($this->model->getPayload());
    }

    public function testPayloadIsMutable()
    {
        $this->model->setPayload('foo');
        $this->assertEquals('foo', $this->model->getPayload());
    }

    public function invalidPayloads()
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
            'array'      => array(array()),
            'stdclass'   => array(new stdClass),
        );
    }

    public function invalidApiProblemPayloads()
    {
        $payloads = $this->invalidPayloads();
        $payloads['hal-collection'] = array(new HalCollection(array(), 'collection/route', 'item/route'));
        $payloads['hal-item'] = array(new HalResource(array(), 'id', 'route'));
        return $payloads;
    }

    /**
     * @dataProvider invalidApiProblemPayloads
     */
    public function testIsApiProblemReturnsFalseForInvalidValues($payload)
    {
        $this->model->setPayload($payload);
        $this->assertFalse($this->model->isApiProblem());
    }

    public function testIsApiProblemReturnsTrueForApiProblemPayload()
    {
        $problem = new ApiProblem(401, 'Unauthorized');
        $this->model->setPayload($problem);
        $this->assertTrue($this->model->isApiProblem());
    }

    public function invalidHalCollectionPayloads()
    {
        $payloads = $this->invalidPayloads();
        $payloads['api-problem'] = array(new ApiProblem(401, 'unauthorized'));
        $payloads['hal-item'] = array(new HalResource(array(), 'id', 'route'));
        return $payloads;
    }

    /**
     * @dataProvider invalidHalCollectionPayloads
     */
    public function testIsHalCollectionReturnsFalseForInvalidValues($payload)
    {
        $this->model->setPayload($payload);
        $this->assertFalse($this->model->isHalCollection());
    }

    public function testIsHalCollectionReturnsTrueForHalCollectionPayload()
    {
        $collection = new HalCollection(array(), 'collection/route', 'item/route');
        $this->model->setPayload($collection);
        $this->assertTrue($this->model->isHalCollection());
    }

    public function invalidHalResourcePayloads()
    {
        $payloads = $this->invalidPayloads();
        $payloads['api-problem'] = array(new ApiProblem(401, 'unauthorized'));
        $payloads['hal-collection'] = array(new HalCollection(array(), 'collection/route', 'item/route'));
        return $payloads;
    }

    /**
     * @dataProvider invalidHalResourcePayloads
     */
    public function testIsHalResourceReturnsFalseForInvalidValues($payload)
    {
        $this->model->setPayload($payload);
        $this->assertFalse($this->model->isHalResource());
    }

    public function testIsHalResourceReturnsTrueForHalResourcePayload()
    {
        $item = new HalResource(array(), 'id', 'route');
        $this->model->setPayload($item);
        $this->assertTrue($this->model->isHalResource());
    }
}
