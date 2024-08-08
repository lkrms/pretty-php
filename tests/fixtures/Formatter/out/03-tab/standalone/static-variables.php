<?php
function foo()
{
	static $bar = 0;

	function () use (&$bar) {
		$bar++;
	};

	static $baz = 0;

	#[Attr()]
	function () use (&$baz) {
		$baz++;
	};

	static $qux = 0;

	new class($qux) {
		function __construct(&$qux)
		{
			$qux++;
		}
	};
}
