<?php
namespace foo;

use My\Full\Classname as Another;

// this is the same as use My\Full\NSname as NSname
use My\Full\NSname;

// importing a global class
use ArrayObject;

// importing a function
use function My\Full\functionName;

// aliasing a function
use function My\Full\functionName as func;

// importing a constant
use const My\Full\CONSTANT;

$obj = new namespace\Another;    // instantiates object of class foo\Another
$obj = new Another;              // instantiates object of class My\Full\Classname
NSname\subns\func();             // calls function My\Full\NSname\subns\func
$a = new ArrayObject(array(1));  // instantiates object of class ArrayObject
                                 // without the "use ArrayObject" we would instantiate an object of class foo\ArrayObject
func();                          // calls function My\Full\functionName
echo CONSTANT;                   // echoes the value of My\Full\CONSTANT
?>