<?php
function foo(&$var) { }

foo($a); // $a is "created" and assigned to null

$b = array();
foo($b['b']);
var_dump(array_key_exists('b', $b)); // bool(true)

$c = new stdClass;
foo($c->d);
var_dump(property_exists($c, 'd')); // bool(true)
?>