<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfullyTest\Plugin\TestAsset;

class Resource
{
    public $id;
    public $name;

    public $first_child;
    public $second_child;

    public function __construct($id, $name)
    {
        $this->id   = $id;
        $this->name = $name;
    }
}
