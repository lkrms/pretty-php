<?php

// This is an entirely legal Enum definition.
enum Direction implements ArrayAccess
{
	case Up;
	case Down;

	public function offsetExists($offset): bool
	{
		return false;
	}

	public function offsetGet($offset): mixed
	{
		return null;
	}

	public function offsetSet($offset, $value): void
	{
		throw new Exception();
	}

	public function offsetUnset($offset): void
	{
		throw new Exception();
	}
}

class Foo
{
	// This is allowed.
	const DOWN = Direction::Down;

	// This is disallowed, as it may not be deterministic.
	const UP = Direction::Up['short'];

	// Fatal error: Cannot use [] on enums in constant expression
}

// This is entirely legal, because it's not a constant expression.
$x = Direction::Up['short'];
var_dump('$x is ' . var_export($x, true));

$foo = new Foo();
?>