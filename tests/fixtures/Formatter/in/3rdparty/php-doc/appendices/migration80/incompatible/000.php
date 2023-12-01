<?php
// Replace
function my_error_handler($err_no, $err_msg, $filename, $linenum) {
    if (error_reporting() == 0) {
        return false;
    }
    // ...
}

// With
function my_error_handler($err_no, $err_msg, $filename, $linenum) {
    if (!(error_reporting() & $err_no)) {
        return false;
    }
    // ...
}
?>