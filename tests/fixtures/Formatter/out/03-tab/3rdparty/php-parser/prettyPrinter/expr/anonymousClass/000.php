<?php

new class {};
new class extends A implements B, C {};
new class($a) extends A {
	private $a;

	public function __construct($a)
	{
		$this->a = $a;
	}
};
new readonly class {};
