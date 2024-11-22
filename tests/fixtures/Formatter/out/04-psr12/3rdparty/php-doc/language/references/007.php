<?php

function foo(&$var)
{
    $var++;
}

$a = 5;
foo($a);

?>