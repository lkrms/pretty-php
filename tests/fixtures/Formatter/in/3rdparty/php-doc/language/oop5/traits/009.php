<?php

trait StaticExample
{
    public static function doSomething()
    {
        return 'Doing something';
    }
}

class Example
{
    use StaticExample;
}

echo Example::doSomething();

?>