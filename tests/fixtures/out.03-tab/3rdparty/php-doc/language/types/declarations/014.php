<?php declare(strict_types=1);

function sum($a, $b): int
{
	return $a + $b;
}

var_dump(sum(1, 2));
var_dump(sum(1, 2.5));
?>