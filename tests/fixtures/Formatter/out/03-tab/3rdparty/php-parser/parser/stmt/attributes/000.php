<?php

#[
	A1,
	A2(),
	A3(0),
	A4(x: 1),
]
function a() {}

#[A5]
class C
{
	#[A6]
	public function m(
		#[A7] $param,
	) {}

	#[A14]
	public $prop;
}

#[A8]
interface I {}

#[A9]
trait T {}

$x = #[A10] function () {};
$y = #[A11] fn() => 0;
$a = #[A12] static function () {};
$b = #[A13] static fn() => 0;
