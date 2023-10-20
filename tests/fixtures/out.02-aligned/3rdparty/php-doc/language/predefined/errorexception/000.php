<?php
function exception_error_handler(int $errno, string $errstr, string $errfile = null, int $errline)
{
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
}

set_error_handler(exception_error_handler(...));
// Prior to PHP 8.1.0 and the introduction of the first class callable syntax, the following call must be used instead
// set_error_handler(__NAMESPACE__ . "\\exception_error_handler");

/* Trigger exception */
strpos();
?>