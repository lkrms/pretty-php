<?php

class Test
{
	public function __construct(
		public readonly int $i = 0,
		public readonly array $ary = [],
	) {}
}

$test = new Test;
$test->i += 1;
$test->i++;
++$test->i;
$test->ary[] = 1;
$test->ary[0][] = 1;
$ref = &$test->i;
$test->i = &$ref;
byRef($test->i);
foreach ($test as &$prop);
?>