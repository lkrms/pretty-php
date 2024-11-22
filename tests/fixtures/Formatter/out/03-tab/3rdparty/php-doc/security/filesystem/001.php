<?php

// Removes a file from anywhere on the hard drive that
// the PHP user has access to. If PHP has root access:
$username = $_POST['user_submitted_name'];  // "../etc"
$userfile = $_POST['user_submitted_filename'];  // "passwd"
$homedir = "/home/$username";  // "/home/../etc"

unlink("$homedir/$userfile");  // "/home/../etc/passwd"

echo 'The file has been deleted!';

?>