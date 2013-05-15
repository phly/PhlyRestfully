<?php

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
