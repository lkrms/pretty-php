<?php

function foo(&$var)
{
    $var++;
}

function bar()  // Note the missing &
{
    $a = 5;
    return $a;
}

foo(bar());  // Produces a notice

foo($a = 5);  // Expression, not variable
foo(5);       // Produces fatal error

class Foobar {}

foo(new Foobar())  // Produces a notice as of PHP 7.0.7
                   // Notice: Only variables should be passed by reference
?>