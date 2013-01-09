<?php

namespace PhlyRestfullyTest\TestAsset;

use JsonSerializable as JsonSerializableInterface;

class JsonSerializable implements JsonSerializable
{
    public function jsonSerialize()
    {
        return array();
    }
}
