<?php
$large_number = 2147483647;
var_dump($large_number);  // int(2147483647)

$large_number = 2147483648;
var_dump($large_number);  // float(2147483648)

$million = 1000000;
$large_number = 50000 * $million;
var_dump($large_number);  // float(50000000000)
?>