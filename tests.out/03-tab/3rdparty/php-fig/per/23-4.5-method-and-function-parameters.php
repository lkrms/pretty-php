<?php declare(strict_types=1);

namespace Vendor\Package;

class ReturnTypeVariations
{
	public function functionName(int $arg1, $arg2): string
	{
		return 'foo';
	}

	public function anotherFunction(
		string $foo,
		string $bar,
		int $baz,
	): string {
		return 'foo';
	}
}
