<?php
class A {
    function f() { echo get_class($this); }
}

class B {
    function f() { A::f(); }
}

(new B)->f();
?>