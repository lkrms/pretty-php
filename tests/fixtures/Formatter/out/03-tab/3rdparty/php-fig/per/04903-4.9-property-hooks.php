<?php

class Example
{
	public function __construct(
		public string $name {
			set => ucfirst($value);
		}
	) {}
}
