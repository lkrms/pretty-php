<?php

class A
{
	private function foo()
	{
		echo "Success!\n";
	}

	public function test()
	{
		$this->foo();
		static::foo();
	}
}

class B extends A
{
	/*
	 * foo() will be copied to B, hence its scope will still be A and
	 * the call be successful
	 */
}

class C extends A
{
	private function foo()
	{
		/* Original method is replaced; the scope of the new one is C */
	}
}

$b = new B();
$b->test();

$c = new C();
try {
	$c->test();
} catch (Error $e) {
	echo $e->getMessage();
}

?>