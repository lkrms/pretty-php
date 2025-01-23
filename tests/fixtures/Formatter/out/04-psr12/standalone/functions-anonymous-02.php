<?php
function () {
    return;
};
function &() {
    return;
};
function (
    MyClass|string|null $foo,
    $bar,
    $baz
) {
    return;
};
function &(
    MyClass|string|null $foo,
    $bar,
    $baz
) {
    return;
};
fn&()
    => null;
$foo = function () {
    return;
};
$foo = function &() {
    return;
};
$foo = fn&()
    => null;
fn(
    MyClass|string|null $foo,
    $bar,
    $baz
) => null;
fn&(
    MyClass|string|null $foo,
    $bar,
    $baz
) => null;
