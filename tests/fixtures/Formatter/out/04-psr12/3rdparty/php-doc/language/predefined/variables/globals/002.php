<?php

// Before PHP 8.1.0
$a = 1;

$globals = $GLOBALS;  // Ostensibly by-value copy
$globals['a'] = 2;
var_dump($a);  // int(2)

// As of PHP 8.1.0
// this no longer modifies $a. The previous behavior violated by-value semantics.
$globals = $GLOBALS;
$globals['a'] = 1;

// To restore the previous behavior, iterate its copy and assign each property back to $GLOBALS.
foreach ($globals as $key => $value) {
    $GLOBALS[$key] = $value;
}

?>