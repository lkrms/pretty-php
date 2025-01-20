<?php

trait T
{
	public static $counter = 1;
}

class A
{
	use T;

	public static function incrementCounter()
	{
		static::$counter++;
	}
}

class B extends A
{
	use T;
}

A::incrementCounter();

echo A::$counter, "\n";
echo B::$counter, "\n";

?>