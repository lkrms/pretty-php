<?php
trait MyTrait
{
    abstract private function neededByTrait(): string;
}

class MyClass
{
    use MyTrait;

    // Error, because of return type mismatch.
    private function neededByTrait(): int
    {
        return 42;
    }
}
?>