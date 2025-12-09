<?php
$array = [
    [1, 2, 5],
    [3, 4, 6],
];

foreach ($array as [$a, $b]) {
    // Note that there is no $c here.
    echo "$a $b\n";
}

foreach ($array as [,, $c]) {
    // Skipping over $a and $b
    echo "$c\n";
}
?>