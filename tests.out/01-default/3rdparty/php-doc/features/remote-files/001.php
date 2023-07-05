<?php
$file = fopen('ftp://ftp.example.com/incoming/outputfile', 'w');
if (!$file) {
    echo "<p>Unable to open remote file for writing.\n";
    exit;
}
/* Write the data here. */
fwrite($file, $_SERVER['HTTP_USER_AGENT'] . "\n");
fclose($file);
?>