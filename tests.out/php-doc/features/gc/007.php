<?php
$a = array('one');
$a[] = &$a;
xdebug_debug_zval('a');
?>