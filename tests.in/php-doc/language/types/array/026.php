<?php

$arr1 = [1, 2, 3];
$arr2 = ['a' => 4];
$arr3 = [...$arr1, ...$arr2];
// Fatal error: Uncaught Error: Cannot unpack array with string keys in example.php:5

$arr4 = [1, 2, 3];
$arr5 = [4, 5];
$arr6 = [...$arr4, ...$arr5]; // works. [1, 2, 3, 4, 5]
?>