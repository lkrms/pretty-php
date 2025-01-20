<?php

abstract class A
{
	// This provides a default (but overridable) set implementation,
	// and requires child classes to provide a get implementation
	abstract public string $foo {
		get;
		set {
			$this->foo = $value;
		}
	}
}

?>