<?php

namespace PhlyRestfully;

use Zend\View\Model\JsonModel;

/**
 * Simple extension to facilitate the specialized JsonStrategy in this MOdule.
 */
class RestfulJsonModel extends JsonModel
{
    public function isProblemApi()
    {
        $variables = $this->getVariables();
        $keys      = array_keys($variables);
        $expected  = array('describedBy', 'title');
        $test      = array_intersect($expected, $keys);
        if ($test == $expected) {
            return true;
        }
        return false;
    }

    public function isHal()
    {
        $variables = $this->getVariables();
        $keys     = array_keys($variables);
        if (in_array('_links', $keys)) {
            return true;
        }
        return false;
    }
}
