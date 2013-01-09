<?php

namespace PhlyRestfullyTest\TestAsset;

class ArraySerializable
{
    public function getHijinx()
    {
        return 'should not get this';
    }

    public function getArrayCopy()
    {
        return array('foo' => 'bar');
    }
}
