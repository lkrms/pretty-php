<?php
function () {
    return;
};

function &()
{
    return;
};
function ((Countable&ArrayAccess)|MyClass|string|null $foo,
          $bar,
          $baz) {
    return;
};

function &((Countable&ArrayAccess)|MyClass|string|null $foo,
    $bar,
    $baz)
{
    return;
};
fn &() =>
    null;
$foo = function () {
    return;
};
$foo = function &() {
    return;
};
$foo = fn &() =>
           null;
fn((Countable&ArrayAccess)|MyClass|string|null $foo,
   $bar,
   $baz) => null;
fn &((Countable & ArrayAccess) | MyClass | string | null $foo,
    $bar,
    $baz) => null;
