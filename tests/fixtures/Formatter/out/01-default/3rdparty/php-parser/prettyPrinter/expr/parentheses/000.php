<?php

echo 'abc' . 'cde' . 'fgh';
echo 'abc' . ('cde' . 'fgh');

echo ('abc' . 1) + 2 . 'fgh';
echo 'abc' . (1 + 2) . 'fgh';

echo 1 * 2 + 3 / 4 % 5 . 6;
echo 1 * (2 + 3) / (4 % (5 . 6));

$a = $b = $c = $d = $f && true;
($a = $b = $c = $d = $f) && true;
$a = $b = $c = $d = $f and true;
$a = $b = $c = $d = ($f and true);

$a ? $b : $c ? $d : $e ? $f : $g;
$a ? $b : ($c ? $d : ($e ? $f : $g));
$a ? $b ? $c : $d : $f;
$a === $b ? $c : $d;

$a ?? $b ?? $c;
($a ?? $b) ?? $c;
$a ?? ($b ? $c : $d);
$a || ($b ?? $c);

(1 > 0) > (1 < 0);
++$a + $b;
$a + $b++;

$a ** $b ** $c;
($a ** $b) ** $c;
-1 ** 2;

yield from $a and yield from $b;
yield from ($a and yield from $b);

print ($a and print $b);
clone ($a + $b);
(throw $a) + $b;

-(-$a);
+(+$a);
-(--$a);
+(++$a);
-(--$a) ** $b;
+(++$a) ** $b;

!$a = $b;
++$a ** $b;
$a ** $b++;
$a . ($b = $c) . $d;
!($a = $b) || $c;
(fn() => $a) || $b;
($a = $b and $c) + $d;
$a ** ($b instanceof $c);
($a = $b) instanceof $c;
[$a and $b => $c];
// TODO: This prints redundant parentheses
[include $a => $c];
