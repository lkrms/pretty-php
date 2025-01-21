<?php
function () {
    return;
};
function &() {
    return;
};
function (
    ?string $foo,
    $bar,
    $baz
) {
    return;
};
function &(
    ?string $foo,
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
    ?string $foo,
    $bar,
    $baz
) => null;
fn&(
    ?string $foo,
    $bar,
    $baz
) => null;
function () use (
    $foo,
    $bar,
    $baz
) {
    return;
};
function &() use (
    $foo,
    $bar,
    $baz
) {
    return;
};
function (?string $alpha, $bravo) use (
    $foo,
    $bar,
    $baz
) {
    return;
};
function &(?string $alpha, $bravo) use (
    $foo,
    $bar,
    $baz
) {
    return;
};
function (
    ?string $alpha,
    $bravo,
    $charlie
) use (
    $foo,
    $bar,
    $baz
) {
    return;
};
function &(
    ?string $alpha,
    $bravo,
    $charlie
) use (
    $foo,
    $bar,
    $baz
) {
    return;
};
