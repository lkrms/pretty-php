<?php
$array = [0, 1, 2];
foreach ($array as &$val) {
    var_dump(current($array));
}
?>