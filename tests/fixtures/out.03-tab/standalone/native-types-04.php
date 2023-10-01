<?php declare(strict_types=1);

class MyClass
{
	public(Countable & ArrayAccess)|MyClass|string|null $Foo;

	public string|MyClass|(Countable & ArrayAccess)|null $Bar;

	public const MY_CONSTANT = 'my constant';

	public function MyMethod(
		$mixed,
		?int $nullableInt,
		string $string,
		Countable&ArrayAccess $intersection,
		MyClass $class,
		?MyClass $nullableClass,
		?MyClass &$nullableClassByRef,
		?MyClass $nullableAndOptionalClass = null,
		string $optionalString = MyClass::MY_CONSTANT,
		string|MyClass $union = SELF::MY_CONSTANT,
		string|MyClass|null $nullableUnion = 'literal',
		array|MyClass $optionalArrayUnion = ['key' => 'value'],
		string|MyClass|null &$nullableUnionByRef = null,
		string|MyClass|(Countable & ArrayAccess) $dnf = SELF::MY_CONSTANT,
		string|MyClass|(Countable & ArrayAccess)|null $nullableDnf = 'literal',
		array|MyClass|(Countable & ArrayAccess) $optionalArrayDnf = ['key' => 'value'],
		string|MyClass|(Countable & ArrayAccess)|null &$nullableDnfByRef = null,
		(MyClass & Countable)|(MyClass & ArrayAccess) &$dnfByRef = null,
		string&...$variadicByRef
	): MyClass|string|null {
		return null;
	}

	public function foo(): (Countable & ArrayAccess)|MyClass|string|null
	{
		return $this->Foo;
	}

	public function bar(): string|MyClass|(Countable & ArrayAccess)|null
	{
		return $this->Bar;
	}

	public function qux(string|MyClass|(Countable & ArrayAccess)|null $qux, (Countable & ArrayAccess)|MyClass|string|null $quux)
	{
		//
	}

	public function quux((Countable & ArrayAccess)|MyClass|string|null $quux, string|MyClass|(Countable & ArrayAccess)|null $qux)
	{
		//
	}
}
