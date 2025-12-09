<?php
// Get the first character of a string
$str   = 'This is a test.';
$first = $str[0];
var_dump($first);

// Get the third character of a string
$third = $str[2];
var_dump($third);

// Get the last character of a string.
$str  = 'This is still a test.';
$last = $str[strlen($str) - 1];
var_dump($last);

// Modify the last character of a string
$str                   = 'Look at the sea';
$str[strlen($str) - 1] = 'e';
var_dump($str);
?>