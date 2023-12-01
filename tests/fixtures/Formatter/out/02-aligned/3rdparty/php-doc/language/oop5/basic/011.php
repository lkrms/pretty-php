<?php
class Foo
{
    public $bar;

    public function __construct()
    {
        $this->bar = function () {
            return 42;
        };
    }
}

$obj = new Foo();

echo ($obj->bar)(), PHP_EOL;
