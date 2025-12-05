<?php

function &collector()
{
    static $collection = array();
    return $collection;
}

$collection = &collector();
// Now the $collection is a referenced variable that references the static array inside the function

$collection[] = 'foo';

print_r(collector());
// Array
// (
//    [0] => foo
// )

?>