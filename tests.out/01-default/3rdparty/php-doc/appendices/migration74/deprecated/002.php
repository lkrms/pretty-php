<?php
// ReflectionClass::export(Foo::class, false) is:
echo new ReflectionClass(Foo::class), "\n";

// $str = ReflectionClass::export(Foo::class, true) is:
$str = (string) new ReflectionClass(Foo::class);
?>