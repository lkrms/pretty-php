<?php

function test(
    bool $a1,
    int $a2,
    float $a3,
    string $a4,  // PHP 7.0
    iterable $a5,  // PHP 7.1
    object $a6,  // PHP 7.2
    mixed $a7,  // PHP 8.0
    null $a8,  // PHP 8.0
    false $a9,  // PHP 8.0
): void {}  // PHP 7.1

function test2(): never {}  // PHP 8.1
