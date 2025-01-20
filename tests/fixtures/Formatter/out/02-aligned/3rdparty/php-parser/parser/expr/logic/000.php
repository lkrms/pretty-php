<?php

// boolean ops
$a && $b;
$a || $b;
!$a;
!!$a;

// logical ops
$a and $b;
$a or $b;
$a xor $b;

// precedence
$a && $b || $c && $d;
$a && ($b || $c) && $d;

$a = $b || $c;
$a = $b or $c;
