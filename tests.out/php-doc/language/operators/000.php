<?php
$a = 3 * 3 % 5;                // (3 * 3) % 5 = 4
                               // ternary operator associativity differs from C/C++
$a = true ? 0 : true ? 1 : 2;  // (true ? 0 : true) ? 1 : 2 = 2 (prior to PHP 8.0.0)

$a = 1;
$b = 2;
$a = $b += 3;  // $a = ($b += 3) -> $a = 5, $b = 5
?>