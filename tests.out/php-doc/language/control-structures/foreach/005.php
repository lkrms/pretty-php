<?php
$array = [
    [1, 2],
    [3, 4],
];

foreach ($array as list($a)) {
    // Note that there is no $b here.
    echo "$a\n";
}
?>