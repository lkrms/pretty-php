<?php

use Foo\{Bar,
	Baz, Qux};
use Foo2\{Bar as Bar2,
	Baz as Baz2};
use Foo3\Bar as Bar3,
	Foo3\Baz as Baz3,
	Foo3\Qux as Qux3;

use function Foo\BarF,
	Foo\BazF,
	Foo\QuxF;

use const Foo\BarC,
	Foo\BazC,
	Foo\QuxC;

const FOO = 1,
	BAR = 2,
	BAZ = 4;

class Foo implements
	Bar3,
	Baz3, Qux3
{
	use Bar,
		Baz,
		Qux,
		Bar2,
		Baz2 {
			Qux::foo insteadof Bar,
				Baz,
				Bar2, Baz2;
		}

	public const FOO = 1,
		BAR = 2,
		BAZ = 4;

	public const QUX =
			self::FOO
			| self::BAR
			| self::BAZ,
		QUUX =
			self::_FOO
			| self::_BAR
			| self::_BAZ;

	public int $Foo = self::FOO,
		$Bar = self::BAR,
		$Baz = self::BAZ;

	public int $Qux = self::QUX,
		$Quux = self::FOO
			| self::BAR
			| self::BAZ,
		$Quuux = self::_FOO
			| self::_BAR
			| self::_BAZ;
}

function foo()
{
	global $foo,
		$bar, $baz;
	static $foo2, $bar2,
		$baz2;
}
