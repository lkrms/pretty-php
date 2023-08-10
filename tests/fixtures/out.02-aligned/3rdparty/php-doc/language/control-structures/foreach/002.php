<?php
foreach (array(1, 2, 3, 4) as &$value) {
    $value = $value * 2;
}
?>