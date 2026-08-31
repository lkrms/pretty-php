<?php

if ($a === $b) {
    $foo = $bar ?? $a ?? $b;
} elseif ($a > $b) {
    $foo = $a + $b * $c;
} else {
    $foo = $a |> log(...) |> round(...);
}
