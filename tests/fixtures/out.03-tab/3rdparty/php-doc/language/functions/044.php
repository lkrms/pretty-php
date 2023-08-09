<?php

class Foo
{
	public function getPrivateMethod()
	{
		return [$this, 'privateMethod'];
	}

	private function privateMethod()
	{
		echo __METHOD__, "\n";
	}
}

$foo = new Foo;
$privateMethod = $foo->getPrivateMethod();
$privateMethod();
// Fatal error: Call to private method Foo::privateMethod() from global scope
// This is because call is performed outside from Foo and visibility will be checked from this point.

class Foo1
{
	public function getPrivateMethod()
	{
		// Uses the scope where the callable is acquired.
		return $this->privateMethod(...);  // identical to Closure::fromCallable([$this, 'privateMethod']);
	}

	private function privateMethod()
	{
		echo __METHOD__, "\n";
	}
}

$foo1 = new Foo1;
$privateMethod = $foo1->getPrivateMethod();
$privateMethod();  // Foo1::privateMethod
?>