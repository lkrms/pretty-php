<?php

new class {
    public function test() {}
};
new class extends A implements B, C {};
new class() {
    public $foo;
};
new class($a, $b) extends A {
    use T;
};

class A
{
    public function test()
    {
        return new class($this) extends A {
            const A = 'B';
        };
    }
}
