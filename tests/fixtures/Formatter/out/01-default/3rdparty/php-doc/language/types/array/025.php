<?php
// string key
$arr1 = ['a' => 1];
$arr2 = ['a' => 2];
$arr3 = ['a' => 0, ...$arr1, ...$arr2];
var_dump($arr3);  // ["a" => 2]

// integer key
$arr4 = [1, 2, 3];
$arr5 = [4, 5, 6];
$arr6 = [...$arr4, ...$arr5];
var_dump($arr6);  // [1, 2, 3, 4, 5, 6]
// Which is [0 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6]
// where the original integer keys have not been retained.
?>