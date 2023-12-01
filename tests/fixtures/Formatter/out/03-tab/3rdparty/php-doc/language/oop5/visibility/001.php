<?php

/**
 * Define MyClass
 */
class MyClass
{
	// Declare a public constructor
	public function __construct() {}

	// Declare a public method
	public function MyPublic() {}

	// Declare a protected method
	protected function MyProtected() {}

	// Declare a private method
	private function MyPrivate() {}

	// This is public
	function Foo()
	{
		$this->MyPublic();
		$this->MyProtected();
		$this->MyPrivate();
	}
}

$myclass = new MyClass;
$myclass->MyPublic();  // Works
$myclass->MyProtected();  // Fatal Error
$myclass->MyPrivate();  // Fatal Error
$myclass->Foo();  // Public, Protected and Private work

/**
 * Define MyClass2
 */
class MyClass2 extends MyClass
{
	// This is public
	function Foo2()
	{
		$this->MyPublic();
		$this->MyProtected();
		$this->MyPrivate();  // Fatal Error
	}
}

$myclass2 = new MyClass2;
$myclass2->MyPublic();  // Works
$myclass2->Foo2();  // Public and Protected work, not Private

class Bar
{
	public function test()
	{
		$this->testPrivate();
		$this->testPublic();
	}

	public function testPublic()
	{
		echo "Bar::testPublic\n";
	}

	private function testPrivate()
	{
		echo "Bar::testPrivate\n";
	}
}

class Foo extends Bar
{
	public function testPublic()
	{
		echo "Foo::testPublic\n";
	}

	private function testPrivate()
	{
		echo "Foo::testPrivate\n";
	}
}

$myFoo = new Foo();
$myFoo->test();  // Bar::testPrivate
// Foo::testPublic
?>