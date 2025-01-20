<?php

abstract class A
{
	var $a;
	static $b;

	abstract function c();
	final function d() {}
	static function e() {}
	final static function f() {}
	function g() {}
}
