<?php

$a = new class extends B implements C, D, E {};

$a = new class extends B implements
    C,
    D,
    E {};

$a = new class extends B implements C, D, E {
    function f(string $h, int $i, $j) {}

    function g($k) {}

    function z() {}
};

$a = new class extends B implements
    C,
    D,
    E
{
    function f(string $h, int $i, $j)
    {
        $this->l($h, $i);
        $j();
    }

    function g($k)
    {
        echo $k;
    }

    function z() {}
};
