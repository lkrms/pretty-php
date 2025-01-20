<?php
function ($a) {
	$a;
};
function ($a) use ($b) {};
function () use ($a, &$b) {};
function &($a) {};
static function () {};
function ($a): array {};
function () use ($a): \Foo\Bar {};
