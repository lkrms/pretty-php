<?php

// An example callback function
function my_callback_function()
{
	echo 'hello world!', PHP_EOL;
}

// An example callback method
class MyClass
{
	static function myCallbackMethod()
	{
		echo 'Hello World!', PHP_EOL;
	}
}

// Type 1: Simple callback
call_user_func('my_callback_function');

// Type 2: Static class method call
call_user_func(['MyClass', 'myCallbackMethod']);

// Type 3: Object method call
$obj = new MyClass();
call_user_func([$obj, 'myCallbackMethod']);

// Type 4: Static class method call
call_user_func('MyClass::myCallbackMethod');

// Type 5: Static class method call using ::class keyword
call_user_func([MyClass::class, 'myCallbackMethod']);

// Type 6: Relative static class method call
class A
{
	public static function who()
	{
		echo 'A', PHP_EOL;
	}
}

class B extends A
{
	public static function who()
	{
		echo 'B', PHP_EOL;
	}
}

call_user_func(['B', 'parent::who']);  // deprecated as of PHP 8.2.0

// Type 7: Objects implementing __invoke can be used as callables
class C
{
	public function __invoke($name)
	{
		echo 'Hello ', $name;
	}
}

$c = new C();
call_user_func($c, 'PHP!');
?>