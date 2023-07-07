<?php
$a   = "dangerous\name";  // \n is a newline inside double quoted strings!
$obj = new $a;

$a   = 'not\at\all\dangerous';  // no problems here.
$obj = new $a;
?>