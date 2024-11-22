<?php
$large_number = 50000000000000000000;
var_dump($large_number);         // float(5.0E+19)

var_dump(PHP_INT_MAX + 1);       // 32-bit system: float(2147483648)
                                 // 64-bit system: float(9.2233720368548E+18)
?>