<?php
$a->b(fn($c) => [
    'short_key'     => 'value',
    'very_long_key' => $c === SOME_CONST
                           ? $d
                           : $e,
    'key'           => 'value2'
]);

$abc = a($b,
         $c);
$d   = a($b, $c);
