<?php
class Foo
{
	static function bar()
	{
		echo "bar\n";
	}

	function baz()
	{
		echo "baz\n";
	}
}

$func = array('Foo', 'bar');
$func();  // prints "bar"
$func = array(new Foo, 'baz');
$func();  // prints "baz"
$func = 'Foo::bar';
$func();  // prints "bar"
?>