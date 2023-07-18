<?php
interface A
{
	public function foo();
}

interface B
{
	public function bar();
}

interface C extends A, B
{
	public function baz();
}

class D implements C
{
	public function foo() {}

	public function bar() {}

	public function baz() {}
}
?>