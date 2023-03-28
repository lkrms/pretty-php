<?php
interface A
{
    public function foo(string $s): string;

    public function bar(int $i): int;
}

// An abstract class may implement only a portion of an interface.
// Classes that extend the abstract class must implement the rest.
abstract class B implements A
{
    public function foo(string $s): string
    {
        return $s . PHP_EOL;
    }
}

class C extends B
{
    public function bar(int $i): int
    {
        return $i * 2;
    }
}
?>