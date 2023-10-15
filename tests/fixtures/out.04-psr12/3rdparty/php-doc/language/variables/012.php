<?php

function foo()
{
    static $int = 0;  // correct
    static $int = 1 + 2;  // correct
    static $int = sqrt(121);  // wrong  (as it is a function)

    $int++;
    echo $int;
}
?>