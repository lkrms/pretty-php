<?php

$a = $b
	| $c
	| $d;

$a = $b
	| $c & $d
	| $e;

$a = $b
	& $c
	| $d
	& $e;

$a = $b ||
	$c ||
	$d;

$a = $b ||
	$c && $d ||
	$e;

$a = $b &&
	$c ||
	$d &&
	$e;

foo(bar() ||
	qux() ||
	quux());

foo(bar() ||
	qux() && quux() ||
	quuux());

foo(bar() &&
	qux() ||
	quux() &&
	quuux());

foo(bar() &&
	qux() &&
	quux() &&
	!(
		quuux() ||
		quuuux()
	));
