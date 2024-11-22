<?php
class BaseClass
{
	final protected string $test;
}

class ChildClass extends BaseClass
{
	public string $test;
}

// Results in Fatal error: Cannot override final property BaseClass::$test
?>