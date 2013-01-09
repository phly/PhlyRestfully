<?php

namespace PhlyRestfullyTest\TestAsset;

use JsonSerializable as JsonSerializableInterface;

class JsonSerializable implements JsonSerializableInterface
{
    public function jsonSerialize()
    {
        return array('foo' => 'bar');
    }
}
