<?php

// Arrays are compared like this with standard comparison operators as well as the spaceship operator.
function standard_array_compare($op1, $op2)
{
    if (count($op1) < count($op2)) {
        return -1;  // $op1 < $op2
    } elseif (count($op1) > count($op2)) {
        return 1;  // $op1 > $op2
    }
    foreach ($op1 as $key => $val) {
        if (!array_key_exists($key, $op2)) {
            return 1;
        } elseif ($val < $op2[$key]) {
            return -1;
        } elseif ($val > $op2[$key]) {
            return 1;
        }
    }
    return 0;  // $op1 == $op2
}
?>