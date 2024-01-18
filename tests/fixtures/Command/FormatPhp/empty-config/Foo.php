<?php declare(strict_types=1);

namespace Foo\Bar;

/**
 * Summary
 */
class Foo
{
    public int $Bar;

    public function __construct()
    {
        $a = 0;  // Short
        $foo = 1;  // Long
        $quuux = 2;  // Longer
        $this->Bar = 3;  // Longest
    }
}
