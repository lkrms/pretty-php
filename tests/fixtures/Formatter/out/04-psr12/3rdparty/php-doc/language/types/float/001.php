<?php
$a = 1.23456789;
$b = 1.2345678;
$epsilon = 0.00001;

if (abs($a - $b) < $epsilon) {
    echo 'true';
}
?>