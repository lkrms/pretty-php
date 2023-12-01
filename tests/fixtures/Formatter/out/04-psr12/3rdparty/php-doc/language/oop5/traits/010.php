<?php

trait StaticExample
{
    public static $static = 'foo';
}

class Example
{
    use StaticExample;
}

echo Example::$static;
?>