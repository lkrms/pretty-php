<?php
// This:
$a = array('color' => 'red',
           'taste' => 'sweet',
           'shape' => 'round',
           'name'  => 'apple',
           4  // key will be 0
           );

$b = array('a', 'b', 'c');

var_dump($a, $b);

// . . .is completely equivalent with this:
$a          = array();
$a['color'] = 'red';
$a['taste'] = 'sweet';
$a['shape'] = 'round';
$a['name']  = 'apple';
$a[]        = 4;  // key will be 0

$b   = array();
$b[] = 'a';
$b[] = 'b';
$b[] = 'c';

// After the above code is executed, $a will be the array
// array('color' => 'red', 'taste' => 'sweet', 'shape' => 'round',
// 'name' => 'apple', 0 => 4), and $b will be the array
// array(0 => 'a', 1 => 'b', 2 => 'c'), or simply array('a', 'b', 'c').

var_dump($a, $b);
?>