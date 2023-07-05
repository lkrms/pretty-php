<?php
$params = array('level' => 6, 'window' => 15, 'memory' => 9);

$original_text = "This is a test.\nThis is only a test.\nThis is not an important string.\n";
echo "The original text is " . strlen($original_text) . " characters long.\n";

$fp = fopen('test.deflated', 'w');
stream_filter_append($fp, 'zlib.deflate', STREAM_FILTER_WRITE, $params);
fwrite($fp, $original_text);
fclose($fp);

echo "The compressed file is " . filesize('test.deflated') . " bytes long.\n";
echo "The original text was:\n";
/* Use readfile and zlib.inflate to decompress on the fly */
readfile('php://filter/zlib.inflate/resource=test.deflated');

/* Generates output:

The original text is 70 characters long.
The compressed file is 56 bytes long.
The original text was:
This is a test.
This is only a test.
This is not an important string.

 */
?>