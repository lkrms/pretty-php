<?php
namespace Name\Space
{
	const FOO = 42;

	function f()
	{
		echo __FUNCTION__ . "\n";
	}
}

namespace
{
	use function Name\Space\f;

	use const Name\Space\FOO;

	echo FOO . "\n";
	f();
}
?>