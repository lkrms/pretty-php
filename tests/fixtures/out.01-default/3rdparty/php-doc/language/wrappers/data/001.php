<?php
$fp = fopen('data://text/plain;base64,', 'r');
$meta = stream_get_meta_data($fp);

// prints "text/plain"
echo $meta['mediatype'];
?>