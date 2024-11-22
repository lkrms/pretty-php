<?php
class Example
{
    private bool $modified = false;

    public string $foo = 'default value' {
        get => $this->foo . ($this->modified ? ' (modified)' : '');

        set (string $value) {
            $this->foo      = strtolower($value);
            $this->modified = true;
        }
    }
}
?>