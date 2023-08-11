<?php
namespace foo;

class MyClass {}

// using a class from the current namespace as a parameter type
function test(MyClass $parameter_type_example = null) {}

// another way to use a class from the current namespace as a parameter type
function test(\foo\MyClass $parameter_type_example = null) {}

// extending a class from the current namespace
class Extended extends MyClass {}

// accessing a global function
$a = \globalfunc();

// accessing a global constant
$b = \INI_ALL;
?>