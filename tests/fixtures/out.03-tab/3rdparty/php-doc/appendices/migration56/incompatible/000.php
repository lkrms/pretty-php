<?php
class C
{
	const ONE = 1;

	public $array = [
		self::ONE => 'foo',
		'bar',
		'quux',
	];
}

var_dump((new C)->array);
?>