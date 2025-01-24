<?php

namespace Vendor\Package;

abstract class ClassName
{
	protected static string $foo;

	private readonly int $beep;

	protected private(set) string $name;

	protected(set) string $boop;

	abstract protected function zim();

	final public static function bar()
	{
		// ...
	}
}

readonly class ValueObject
{
	// ...
}
