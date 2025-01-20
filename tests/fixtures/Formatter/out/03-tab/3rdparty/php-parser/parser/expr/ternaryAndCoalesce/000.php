<?php

// ternary
$a ? $b : $c;
$a ?: $c;

// precedence
$a ? $b : $c ? $d : $e;
$a ? $b : ($c ? $d : $e);

// null coalesce
$a ?? $b;
$a ?? $b ?? $c;
$a ?? $b ? $c : $d;
$a && $b ?? $c;
