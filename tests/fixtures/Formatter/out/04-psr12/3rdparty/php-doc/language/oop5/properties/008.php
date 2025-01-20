<?php

class Test1
{
    public readonly ?string $prop;

    public function __clone()
    {
        $this->prop = null;
    }

    public function setProp(string $prop): void
    {
        $this->prop = $prop;
    }
}

$test1 = new Test1;
$test1->setProp('foobar');

$test2 = clone $test1;
var_dump($test2->prop);  // NULL
?>