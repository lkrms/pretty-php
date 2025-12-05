<?php
$result = match ($x) {
    foo() => 'value',
    $this->bar() => 'value', // $this->bar() isn't called if foo() === $x
    $this->baz => beep(), // beep() isn't called unless $x === $this->baz
    // etc.
};
?>