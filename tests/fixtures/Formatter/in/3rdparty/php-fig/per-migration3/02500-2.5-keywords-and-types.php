<?php

function foo(int|string $a): User|Product
{
    // ...
}

function complex(array|(ArrayAccess&Traversable) $input): ArrayAccess&Traversable
{
    // ...
}

function veryComplex(
    array
    |(ArrayAccess&Traversable)
    |(Traversable&Countable) $input): ArrayAccess&Traversable
{
    // ...
}
