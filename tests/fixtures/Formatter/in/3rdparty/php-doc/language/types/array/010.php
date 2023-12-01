<?php
$source_array = ['foo', 'bar', 'baz'];

[$foo, $bar, $baz] = $source_array;

echo $foo;    // prints "foo"
echo $bar;    // prints "bar"
echo $baz;    // prints "baz"
?>