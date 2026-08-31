<?php
$a |> $b |> $c;
$a . $b |> $c . $d;
$a |> $b == $c;
$c == $a |> $b;
$a |> (fn($x) => $x) |> (fn($y) => $y);
$a |> fn($x) => $x |> fn($y) => $y;
