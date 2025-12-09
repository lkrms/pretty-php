<?php
$a = array(1 => 'one', 2 => 'two', 3 => 'three');

/*
 * will produce an array that would have been defined as
 * $a = array(1 => 'one', 3 => 'three');
 * and NOT
 * $a = array(1 => 'one', 2 =>'three');
 */
unset($a[2]);
var_dump($a);

$b = array_values($a);
// Now $b is array(0 => 'one', 1 =>'three')
var_dump($b);
?>