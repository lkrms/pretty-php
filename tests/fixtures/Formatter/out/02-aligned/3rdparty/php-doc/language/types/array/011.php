<?php
$source_array = ['foo', 'bar', 'baz'];

[$foo, $bar, $baz] = $source_array;

echo $foo, PHP_EOL;  // prints "foo"
echo $bar, PHP_EOL;  // prints "bar"
echo $baz, PHP_EOL;  // prints "baz"
?>