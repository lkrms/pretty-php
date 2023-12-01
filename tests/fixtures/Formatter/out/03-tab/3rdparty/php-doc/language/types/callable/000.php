<?php

// An example callback function
function my_callback_function()
{
	echo 'hello world!';
}

// An example callback method
class MyClass
{
	static function myCallbackMethod()
	{
		echo 'Hello World!';
	}
}

// Type 1: Simple callback
call_user_func('my_callback_function');

// Type 2: Static class method call
call_user_func(array('MyClass', 'myCallbackMethod'));

// Type 3: Object method call
$obj = new MyClass();
call_user_func(array($obj, 'myCallbackMethod'));

// Type 4: Static class method call
call_user_func('MyClass::myCallbackMethod');

// Type 5: Relative static class method call
class A
{
	public static function who()
	{
		echo "A\n";
	}
}

class B extends A
{
	public static function who()
	{
		echo "B\n";
	}
}

call_user_func(array('B', 'parent::who'));  // A, deprecated as of PHP 8.2.0

// Type 6: Objects implementing __invoke can be used as callables
class C
{
	public function __invoke($name)
	{
		echo 'Hello ', $name, "\n";
	}
}

$c = new C();
call_user_func($c, 'PHP!');
?>