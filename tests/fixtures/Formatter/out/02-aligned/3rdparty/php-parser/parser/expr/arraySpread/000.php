<?php
$array = [1, 2, 3];

function getArr()
{
    return [4, 5];
}

function arrGen()
{
    for ($i = 11; $i < 15; $i++) {
        yield $i;
    }
}

[...[]];
[...[1, 2, 3]];
[...$array];
[...getArr()];
[...arrGen()];
[...new ArrayIterator(['a', 'b', 'c'])];
[0, ...$array, ...getArr(), 6, 7, 8, 9, 10, ...arrGen()];
[0, ...$array, ...$array, 'end'];
