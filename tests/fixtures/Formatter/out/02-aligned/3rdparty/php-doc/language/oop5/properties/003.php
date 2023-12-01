<?php

class Test
{
    public readonly string $prop;

    public function __construct(string $prop)
    {
        // Legal initialization.
        $this->prop = $prop;
    }
}

$test = new Test('foobar');
// Legal read.
var_dump($test->prop);  // string(6) "foobar"

// Illegal reassignment. It does not matter that the assigned value is the same.
$test->prop = 'foobar';
// Error: Cannot modify readonly property Test::$prop
?>