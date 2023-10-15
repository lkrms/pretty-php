<?php

/*
 * This example assumes that www.example.com is configured to parse .php
 * files and not .txt files. Also, 'Works' here means that the variables
 * $foo and $bar are available within the included file.
 */

// Won't work; file.txt wasn't handled by www.example.com as PHP
include 'http://www.example.com/file.txt?foo=1&bar=2';

// Won't work; looks for a file named 'file.php?foo=1&bar=2' on the
// local filesystem.
include 'file.php?foo=1&bar=2';

// Works.
include 'http://www.example.com/file.php?foo=1&bar=2';
?>