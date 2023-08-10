<?php
$ref = 0;
$row = &$ref;
foreach (array(1, 2, 3) as $row) {
    // do something
}
echo $ref;  // 3 - last element of the iterated array
?>