<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace PhlyRestfullyTest\Plugin\TestAsset;

class EmbeddedResourceWithCustomIdentifier
{
    public $custom_id;
    public $name;

    public function __construct($id, $name)
    {
        $this->custom_id = $id;
        $this->name      = $name;
    }
}
