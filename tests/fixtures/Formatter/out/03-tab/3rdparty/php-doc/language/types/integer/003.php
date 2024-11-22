<?php

function foo($value): int
{
	return $value;
}

var_dump(foo(8.1));  // "Deprecated: Implicit conversion from float 8.1 to int loses precision" as of PHP 8.1.0
var_dump(foo(8.1));  // 8 prior to PHP 8.1.0
var_dump(foo(8.0));  // 8 in both cases

var_dump((int) 8.1);  // 8 in both cases
var_dump(intval(8.1));  // 8 in both cases
?>