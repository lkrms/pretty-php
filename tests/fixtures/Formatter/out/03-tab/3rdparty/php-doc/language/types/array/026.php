<?php
// Using short array syntax.
// Also, works with array() syntax.
$arr1 = [1, 2, 3];
$arr2 = [...$arr1];  // [1, 2, 3]
$arr3 = [0, ...$arr1];  // [0, 1, 2, 3]
$arr4 = [...$arr1, ...$arr2, 111];  // [1, 2, 3, 1, 2, 3, 111]
$arr5 = [...$arr1, ...$arr1];  // [1, 2, 3, 1, 2, 3]

function getArr()
{
	return ['a', 'b'];
}

$arr6 = [...getArr(), 'c' => 'd'];  // ['a', 'b', 'c' => 'd']

var_dump($arr1, $arr2, $arr3, $arr4, $arr5, $arr6);
?>