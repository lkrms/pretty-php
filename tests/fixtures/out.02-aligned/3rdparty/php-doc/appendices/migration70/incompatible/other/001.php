<?php
class A
{
    public function test()
    {
        var_dump($this);
    }
}

// Note: Does NOT extend A
class B
{
    public function callNonStaticMethodOfA()
    {
        A::test();
    }
}

(new B)->callNonStaticMethodOfA();
?>