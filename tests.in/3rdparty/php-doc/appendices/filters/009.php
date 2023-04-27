<?php
$param = array('blocks' => 9, 'work' => 0);

echo "The original file is " . filesize('LICENSE') . " bytes long.\n";

$fp = fopen('LICENSE.compressed', 'w');
stream_filter_append($fp, 'bzip2.compress', STREAM_FILTER_WRITE, $param);
fwrite($fp, file_get_contents('LICENSE'));
fclose($fp);

echo "The compressed file is " . filesize('LICENSE.compressed') . " bytes long.\n";

/* Generates output:

The original file is 3288 bytes long.
The compressed file is 1488 bytes long.

 */
?>