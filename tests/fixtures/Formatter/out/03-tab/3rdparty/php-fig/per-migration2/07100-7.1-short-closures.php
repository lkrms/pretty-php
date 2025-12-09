<?php
$func = fn(int $x, int $y): int => $x + $y;

$func = fn(int $x, int $y): int =>
	$x + $y;

$func = fn(
	int $x,
	int $y,
): int =>
	$x + $y;

$result = $collection->reduce(fn(int $x, int $y): int => $x + $y, 0);
