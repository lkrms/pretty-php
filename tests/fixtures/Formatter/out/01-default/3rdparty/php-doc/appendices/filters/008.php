<?php
$original_text = "This is a test.\nThis is only a test.\nThis is not an important string.\n";
echo 'The original text is ' . strlen($original_text) . " characters long.\n";

$fp = fopen('test.deflated', 'w');
/* Here "6" indicates compression level 6 */
stream_filter_append($fp, 'zlib.deflate', STREAM_FILTER_WRITE, 6);
fwrite($fp, $original_text);
fclose($fp);

echo 'The compressed file is ' . filesize('test.deflated') . " bytes long.\n";

/*
 * Generates output:
 *
 * The original text is 70 characters long.
 * The compressed file is 56 bytes long.
 */
?>