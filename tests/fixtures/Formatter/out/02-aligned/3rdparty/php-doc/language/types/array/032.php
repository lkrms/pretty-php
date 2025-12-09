<?php
$colors = array('red', 'blue', 'green', 'yellow');

foreach ($colors as &$color) {
    $color = mb_strtoupper($color);
}
unset($color);  /* ensure that following writes to
$color will not modify the last array element */

print_r($colors);
?>