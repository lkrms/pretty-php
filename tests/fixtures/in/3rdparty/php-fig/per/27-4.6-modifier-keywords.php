<?php

namespace Vendor\Package;

abstract class ClassName
{
    protected static string $foo;

    private readonly int $beep;

    abstract protected function zim();

    final public static function bar()
    {
        // ...
    }
}

readonly class ValueObject
{
    // ...
}
