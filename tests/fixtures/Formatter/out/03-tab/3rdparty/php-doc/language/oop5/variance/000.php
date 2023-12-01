<?php

abstract class Animal
{
	protected string $name;

	public function __construct(string $name)
	{
		$this->name = $name;
	}

	abstract public function speak();
}

class Dog extends Animal
{
	public function speak()
	{
		echo $this->name . ' barks';
	}
}

class Cat extends Animal
{
	public function speak()
	{
		echo $this->name . ' meows';
	}
}
