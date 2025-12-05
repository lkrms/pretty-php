<?php
$a  = 3;
$a += 5;         // sets $a to 8, as if we had said: $a = $a + 5;
$b  = 'Hello ';
$b .= 'There!';  // sets $b to "Hello There!", just like $b = $b . "There!";

var_dump($a, $b);
?>