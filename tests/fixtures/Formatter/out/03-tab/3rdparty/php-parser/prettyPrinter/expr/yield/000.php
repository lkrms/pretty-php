<?php

function gen()
{
	yield;
	yield $a;
	yield $a => $b;
	$a = yield;
	$a = yield $b;
	$a = yield $b => $c;
	yield from $a;
	$a = yield from $b;
	(yield $a) + $b;
	(yield from $a) + $b;
	[yield $a => $b];
	[(yield $a) => $b];
	[$a + (yield $b) => $c];
	yield yield $a => $b;
	yield (yield $a) => $b;
	match ($x) {
		yield $a, (yield $b) => $c,
	};
	yield -$a;
	(yield) - $a;
	yield * $a;
}
