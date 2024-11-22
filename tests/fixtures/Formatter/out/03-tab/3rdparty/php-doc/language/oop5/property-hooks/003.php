<?php
class Example
{
	public string $foo = 'default value' {
		get => $this->foo . ($this->modified ? ' (modified)' : '');
		set => strtolower($value);
	}
}
?>