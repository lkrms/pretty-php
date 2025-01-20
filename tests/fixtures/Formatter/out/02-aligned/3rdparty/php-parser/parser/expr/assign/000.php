<?php
// simple assign
$a = $b;

// combined assign
$a  &= $b;
$a  |= $b;
$a  ^= $b;
$a  .= $b;
$a  /= $b;
$a  -= $b;
$a  %= $b;
$a  *= $b;
$a  += $b;
$a <<= $b;
$a >>= $b;
$a **= $b;
$a ??= $b;

// chained assign
$a = $b *= $c **= $d;

// by ref assign
$a = &$b;

// list() assign
list($a)                 = $b;
list($a,, $b)            = $c;
list($a, list(, $c), $d) = $e;

// inc/dec
++$a;
$a++;
--$a;
$a--;
