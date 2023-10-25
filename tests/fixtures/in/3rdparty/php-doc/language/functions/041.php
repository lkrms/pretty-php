<?php

$x = 1;
$fn = fn() => $x++; // Has no effect
$fn();
var_export($x);  // Outputs 1

?>