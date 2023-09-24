<?php
/* Assignment of scalar variables */
$a = 1;
$b = &$a;
$c = $b;
$c = 7;  // $c is not a reference; no change to $a or $b

/* Assignment of array variables */
$arr = array(1);
$a = &$arr[0];  // $a and $arr[0] are in the same reference set
$arr2 = $arr;  // not an assignment-by-reference!
$arr2[0]++;
/* $a == 2, $arr == array(2) */
/* The contents of $arr are changed even though it's not a reference! */
?>