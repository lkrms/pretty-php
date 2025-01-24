<?php

function beep(string $a, string $b, string $c)
{
    // ...
}

function beep(
    string $a,
    string $b,
    string $c,
) {
    // ...
}

$arr = ['a' => 'A', 'b' => 'B', 'c' => 'C'];

$arr = [
    'a' => 'A',
    'b' => 'B',
    'c' => 'C',
];

$result = match ($a) {
    'foo' => 'Foo',
    'bar' => 'Bar',
    default => 'Baz',
};
