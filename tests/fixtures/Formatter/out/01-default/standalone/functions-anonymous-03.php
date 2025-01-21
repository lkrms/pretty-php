<?php
function () {
    return;
};
function &() {
    return;
};
function (MyClass|string|null $foo,
        Countable&ArrayAccess $bar,
        $baz) {
    return;
};
function &(MyClass|string|null $foo,
        Countable&ArrayAccess $bar,
        $baz) {
    return;
};
fn&() =>
    null;
$foo = function () {
    return;
};
$foo = function &() {
    return;
};
$foo = fn&() =>
    null;
fn(MyClass|string|null $foo,
    Countable&ArrayAccess $bar,
    $baz) => null;
fn&(MyClass|string|null $foo,
    Countable&ArrayAccess $bar,
    $baz) => null;
