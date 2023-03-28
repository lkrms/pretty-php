<?php
$fp = fopen('php://output', 'w');
stream_filter_append($fp, 'string.tolower');
fwrite($fp, "This is a test.\n");
/* Outputs:  this is a test.   */
?>