<?php
class Example
{
	public string $foo {
		get {
			$temp = __PROPERTY__;
			return $this->$temp;  // Doesn't refer to $this->foo, so it doesn't count.
		}
	}
}
?>