<?php

function exception_error_handler($severity, $message, $file, $line)
{
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}

set_error_handler('exception_error_handler');

/* Trigger exception */
strpos();
?>