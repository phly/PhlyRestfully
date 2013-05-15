<?php

namespace PhlyRestfullyTest\Plugin\TestAsset;

class EmbeddedResource
{
    public $id;
    public $name;

    public function __construct($id, $name)
    {
        $this->id   = $id;
        $this->name = $name;
    }
}
