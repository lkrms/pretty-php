<?php
$array = [];
$array["a"] = &$array["b"];
$array["b"] = 1;
var_dump($array);
?>