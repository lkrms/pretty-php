<?php
trait PropertiesTrait
{
	public $same = true;
	public $different1 = false;
	public bool $different2;
	public bool $different3;
}

class PropertiesExample
{
	use PropertiesTrait;

	public $same = true;
	public $different1 = true;  // Fatal error
	public string $different2;  // Fatal error
	readonly protected bool $different3;  // Fatal error
}
?>