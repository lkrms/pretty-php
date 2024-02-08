<?php declare(strict_types=1);

namespace Foo\Bar;

/**
 * Summary
 */
class Bar
{
    public int $Foo;

    public function __construct()
    {
        $a = 0;  // Short
        $foo = 1;  // Long
        $quuux = 2;  // Longer
        $this->Foo = 3;  // Longest
    }
}
