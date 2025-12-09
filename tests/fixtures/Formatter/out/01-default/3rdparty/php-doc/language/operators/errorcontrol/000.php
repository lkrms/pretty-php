<?php
$my_file = @file('non_existent_file') or
    die("Failed opening file: error was '" . error_get_last()['message'] . "'");
?>