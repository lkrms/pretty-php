<?php
trait PropertiesTrait
{
	public $x = 1;
}

class PropertiesExample
{
	use PropertiesTrait;
}

$example = new PropertiesExample;
$example->x;
?>