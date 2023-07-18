<?php

namespace Vendor\Package;

abstract class ClassName
{
    protected static readonly string $foo;

    final protected int $beep;

    abstract protected function zim();

    final public static function bar()
    {
        // method body
    }
}

readonly class ValueObject
{
    // ...
}
