<?php
class Foo
{
	public static function aStaticMethod()
	{
		// ...
	}
}

Foo::aStaticMethod();
$classname = 'Foo';
$classname::aStaticMethod();
?>