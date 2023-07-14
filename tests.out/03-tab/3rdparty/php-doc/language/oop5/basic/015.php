<?php

class Base
{
	public function foo(int $a = 5)
	{
		echo "Valid\n";
	}
}

class Extend extends Base
{
	function foo(int $a)
	{
		parent::foo($a);
	}
}
