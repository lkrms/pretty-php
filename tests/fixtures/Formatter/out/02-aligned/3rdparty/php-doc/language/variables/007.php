<?php
$a = 1;  // global scope

function test()
{
    echo $a;  // Variable $a is undefined as it refers to a local version of $a
}
?>