<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\TestAsset;

use JsonSerializable as JsonSerializableInterface;

/**
 * @subpackage UnitTest
 */
class JsonSerializable implements JsonSerializableInterface
{
    public function jsonSerialize()
    {
        return array('foo' => 'bar');
    }
}
