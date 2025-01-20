<?php

trait CommonTrait
{
	public function method()
	{
		echo 'Hello';
	}
}

class FinalExampleA
{
	use CommonTrait {
		CommonTrait::method as final;  // The 'final' prevents child classes from overriding the method
	}
}

class FinalExampleB extends FinalExampleA
{
	public function method() {}
}

?>