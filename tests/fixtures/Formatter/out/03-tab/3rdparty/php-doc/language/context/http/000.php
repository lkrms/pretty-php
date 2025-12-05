<?php

$postdata = http_build_query(
	[
		'var1' => 'some content',
		'var2' => 'doh',
	]
);

$opts = [
	'http' => [
		'method' => 'POST',
		'header' => 'Content-type: application/x-www-form-urlencoded',
		'content' => $postdata,
	]
];

$context = stream_context_create($opts);

$result = file_get_contents('http://example.com/submit.php', false, $context);

?>