<?php
$a = 1;
$b = 2;

[$b, $a] = [$a, $b];

echo $a;  // prints 2
echo $b;  // prints 1
?>