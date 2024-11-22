<?php

interface A
{
    public function foo();
}

interface B extends A
{
    public function baz(Baz $baz);
}

// This will work
class C implements B
{
    public function foo() {}

    public function baz(Baz $baz) {}
}

// This will not work and result in a fatal error
class D implements B
{
    public function foo() {}

    public function baz(Foo $foo) {}
}
?>