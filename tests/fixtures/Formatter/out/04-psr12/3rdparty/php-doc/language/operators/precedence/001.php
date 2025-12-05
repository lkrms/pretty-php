<?php
$a = true ? 0 : (true ? 1 : 2);
var_dump($a);

// this is not allowed since PHP 8
// $a = true ? 0 : true ? 1 : 2;
?>