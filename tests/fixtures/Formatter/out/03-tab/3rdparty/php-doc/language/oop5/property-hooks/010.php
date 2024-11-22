<?php
class Strings
{
	public string $val;
}

class CaseFoldingStrings extends Strings
{
	public bool $uppercase = true;

	public string $val {
		get => $this->uppercase
			? strtoupper(parent::$val::get())
			: strtolower(parent::$val::get());
	}
}
?>