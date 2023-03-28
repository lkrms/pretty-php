<?php
$a = "new string";
$c = $b = $a;
xdebug_debug_zval('a');
$b = 42;
xdebug_debug_zval('a');
unset($c);
xdebug_debug_zval('a');
?>