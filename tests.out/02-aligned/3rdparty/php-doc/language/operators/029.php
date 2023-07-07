<?php

class MyClass {}

class NotMyClass {}

$a = new MyClass;

var_dump($a instanceof MyClass);
var_dump($a instanceof NotMyClass);
?>