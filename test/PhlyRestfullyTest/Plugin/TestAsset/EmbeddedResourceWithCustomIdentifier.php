<?php

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

