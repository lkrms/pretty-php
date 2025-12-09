<?php
class SimpleClass {}

$instance = new SimpleClass();
var_dump($instance);

// This can also be done with a variable:
$className = 'SimpleClass';
$instance  = new $className();  // new SimpleClass()
var_dump($instance);
?>