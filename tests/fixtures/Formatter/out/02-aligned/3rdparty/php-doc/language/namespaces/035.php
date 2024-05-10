<?php
namespace foo;

use blah\blah as foo;

const FOO = 1;

function my() {}
function foo() {}

function sort(&$a)
{
    \sort($a);  // calls the global function "sort"
    $a = array_flip($a);
    return $a;
}

my();                 // calls "foo\my"
$a   = strlen('hi');  // calls global function "strlen" because "foo\strlen" does not exist
$arr = array(1, 3, 2);
$b   = sort($arr);    // calls function "foo\sort"
$c   = foo();         // calls function "foo\foo" - import is not applied

$a = FOO;      // sets $a to value of constant "foo\FOO" - import is not applied
$b = INI_ALL;  // sets $b to value of global constant "INI_ALL"
?>