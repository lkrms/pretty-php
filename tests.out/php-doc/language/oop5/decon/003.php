<?php

// All allowed:
static $x = new Foo;

const C = new Foo;

function test($param = new Foo) {}

#[AnAttribute(new Foo)]
class Test
{
    public function __construct(
        public $prop = new Foo,
    ) {
    }
}

// All not allowed (compile-time error):
function test(
    $a = new (CLASS_NAME_CONSTANT)(),  // dynamic class name
    $b = new class {},                 // anonymous class
    $c = new A(...[]),                 // argument unpacking
    $d = new B($abc),                  // unsupported constant expression
) {
}
?>