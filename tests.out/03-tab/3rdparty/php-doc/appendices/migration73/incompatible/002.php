<?php
class Test
{
	public static $x = 0;
}

class Test2 extends Test {}

Test2::$x = &$x;
$x = 1;

var_dump(Test::$x, Test2::$x);
// Previously: int(0), int(1)
// Now:        int(1), int(1)
?>