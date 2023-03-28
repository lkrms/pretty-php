<?php

class Foo {
   public function method() {}
   public static function staticmethod() {}
   public function __invoke() {}
}

$obj = new Foo();
$classStr = 'Foo';
$methodStr = 'method';
$staticmethodStr = 'staticmethod';


$f1 = strlen(...);
$f2 = $obj(...);  // invokable object
$f3 = $obj->method(...);
$f4 = $obj->$methodStr(...);
$f5 = Foo::staticmethod(...);
$f6 = $classStr::$staticmethodStr(...);

// traditional callable using string, array
$f7 = 'strlen'(...);
$f8 = [$obj, 'method'](...);
$f9 = [Foo::class, 'staticmethod'](...);
?>