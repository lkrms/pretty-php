<?php
function getArray()
{
    return [1, 2, 3];
}

function squareArray(array &$a)
{
    foreach ($a as &$v) {
        $v **= 2;
    }
}

// Generates a warning in PHP 7.
squareArray((getArray()));
?>