<?php
function foo()
{
	static $int = 0;  // correct
	static $int = 1 + 2;  // correct
	static $int = sqrt(121);  // correct as of PHP 8.3.0

	$int++;
	echo $int;
}
?>