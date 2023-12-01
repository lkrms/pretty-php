<?php

namespace A\B\C;

class Exception extends \Exception {}

$a = new Exception('hi');  // $a is an object of class A\B\C\Exception
$b = new \Exception('hi');  // $b is an object of class Exception

$c = new ArrayObject;  // fatal error, class A\B\C\ArrayObject not found
?>