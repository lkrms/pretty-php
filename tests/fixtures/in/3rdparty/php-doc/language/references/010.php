<?php
function foo(&$var)
{
    $var++;
}
function &bar()
{
    $a = 5;
    return $a;
}
foo(bar());
?>