<?php
// PHP 5 era code that will break.
function handler(Exception $e) { /* ... */ }
set_exception_handler('handler');

// PHP 5 and 7 compatible.
function handler($e) { /* ... */ }

// PHP 7 only.
function handler(Throwable $e) { /* ... */ }
?>