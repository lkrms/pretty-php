<?php

interface MyInterface
{
	public const VALUE = 42;
}

class MyClass implements MyInterface
{
	protected const VALUE = 42;
}
?>