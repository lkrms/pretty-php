<?php

// unary ops
~$a;
+$a;
-$a;

// binary ops
$a & $b;
$a | $b;
$a ^ $b;
$a . $b;
$a / $b;
$a - $b;
$a % $b;
$a * $b;
$a + $b;
$a << $b;
$a >> $b;
$a ** $b;

// associativity
$a * $b * $c;
$a * ($b * $c);

// precedence
$a + $b * $c;
($a + $b) * $c;

// pow is special
$a ** $b ** $c;
($a ** $b) ** $c;
