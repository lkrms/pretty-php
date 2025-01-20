<?php
$source_array = ['foo', 'bar', 'baz'];

// Assign the element at index 2 to the variable $baz
[,, $baz] = $source_array;

echo $baz;  // prints "baz"
?>