<?php
$a = 1;
$b = 2;

[$b, $a] = [$a, $b];

echo $a, PHP_EOL;  // prints 2
echo $b, PHP_EOL;  // prints 1
?>