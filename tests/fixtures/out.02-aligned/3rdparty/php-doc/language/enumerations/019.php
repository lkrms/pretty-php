<?php

class A {}
class B extends A {}

function foo(A $a) {}

function bar(B $b)
{
    foo($b);
}
?>