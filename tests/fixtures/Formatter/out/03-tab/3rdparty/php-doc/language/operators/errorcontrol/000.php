<?php
/* Intentional file error */
$my_file = @file('non_existent_file') or
	die("Failed opening file: error was '" . error_get_last()['message'] . "'");

// this works for any expression, not just functions:
$value = @$cache[$key];
// will not issue a notice if the index $key doesn't exist.

?>