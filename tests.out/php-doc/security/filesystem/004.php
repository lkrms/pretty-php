<?php
$file = $_GET['file'];  // "../../etc/passwd\0"
if (file_exists('/home/wwwrun/' . $file . '.php')) {
    // file_exists will return true as the file /home/wwwrun/../../etc/passwd exists
    include '/home/wwwrun/' . $file . '.php';
    // the file /etc/passwd will be included
}
?>