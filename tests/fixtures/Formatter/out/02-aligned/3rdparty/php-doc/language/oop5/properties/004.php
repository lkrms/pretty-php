<?php
class Test1
{
    public readonly string $prop;
}

$test1       = new Test1;
// Illegal initialization outside of private scope.
$test1->prop = 'foobar';
// Error: Cannot initialize readonly property Test1::$prop from global scope
?>