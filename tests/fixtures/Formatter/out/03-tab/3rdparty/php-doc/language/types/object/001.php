<?php
$obj = (object) array('1' => 'foo');
var_dump(isset($obj->{'1'}));  // outputs 'bool(true)'

// Deprecated as of PHP 8.1
var_dump(key($obj));  // outputs 'string(1) "1"'
?>