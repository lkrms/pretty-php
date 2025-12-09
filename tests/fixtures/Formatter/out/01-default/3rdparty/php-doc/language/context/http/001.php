<?php

$url = 'http://www.example.org/header.php';

$opts = [
    'http' => [
        'method' => 'GET',
        'max_redirects' => '0',
        'ignore_errors' => '1',
    ]
];

$context = stream_context_create($opts);
$stream = fopen($url, 'r', false, $context);

// header information as well as meta data
// about the stream
var_dump(stream_get_meta_data($stream));

// actual data at $url
var_dump(stream_get_contents($stream));
fclose($stream);
?>