<?php

$arr = [
	// Foo
	$foo,
	// Bar
	$bar,
	// Discarded
];
[
	// Foo
	$foo,,
	// Bar
	$bar,
] = $arr;
foo(
	// Foo
	$foo,
	// Bar
	$bar
);
new Foo(
	// Foo
	$foo
);
