<?php
define('CONSTANT', 'Hello world.');
echo CONSTANT;  // outputs "Hello world."
echo Constant;  // Emits an Error: Undefined constant "Constant"
                // Prior to PHP 8.0.0, outputs "Constant" and issues a warning.
?>