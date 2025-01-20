<?php
class Foo
{
    public const BAR  = 'bar';
    private const BAZ = 'baz';
}

$name = 'BAR';
echo Foo::{$name}, PHP_EOL;  // bar
?>