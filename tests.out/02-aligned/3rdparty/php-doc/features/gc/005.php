<?php
$a         = array('meaning' => 'life', 'number' => 42);
$a['life'] = $a['meaning'];
xdebug_debug_zval('a');
?>