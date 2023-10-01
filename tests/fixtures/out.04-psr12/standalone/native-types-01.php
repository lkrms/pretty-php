<?php

declare(strict_types=1);

class MyClass
{
    public MyClass $Foo;
    public ?int $Bar;

    public const MY_CONSTANT = 'my constant';

    public function MyMethod(
        $mixed,
        ?int $nullableInt,
        string $string,
        MyClass $class,
        ?MyClass $nullableClass,
        ?MyClass &$nullableClassByRef,
        ?MyClass $nullableAndOptionalClass = null,
        string $optionalString = MyClass::MY_CONSTANT,
        string &...$variadicByRef
    ): ?MyClass {
        return null;
    }

    public function foo(): MyClass
    {
        return $this->Foo;
    }

    public function bar(): ?int
    {
        return $this->Bar;
    }

    public function qux(MyClass $qux)
    {
        //
    }

    public function quux(?int $quux)
    {
        //
    }
}
