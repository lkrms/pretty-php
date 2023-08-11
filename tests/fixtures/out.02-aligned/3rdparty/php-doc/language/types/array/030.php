<?php
foreach ($colors as &$color) {
    $color = strtoupper($color);
}
unset($color);  /* ensure that following writes to
 $color will not modify the last array element */

print_r($colors);
?>