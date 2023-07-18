<?php

// Brace on the same line
// No arguments
$instance = new class extends \Foo implements \HandleableInterface {
    // Class content
};

// Brace on the next line
// Constructor arguments
$instance = new class($a) extends \Foo implements
    \ArrayAccess,
    \Countable,
    \Serializable
{
    public function __construct(public int $a)
    {
    }
    // Class content
};
