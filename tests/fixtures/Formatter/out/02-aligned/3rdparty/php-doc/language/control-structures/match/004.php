<?php
$result = match ($x) {
    // This match arm:
    $a, $b, $c => 5,
    // Is equivalent to these three match arms:
    $a         => 5,
    $b         => 5,
    $c         => 5,
};
?>