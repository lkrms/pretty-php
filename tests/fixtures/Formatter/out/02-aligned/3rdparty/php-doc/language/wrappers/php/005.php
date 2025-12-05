<?php
$input      = file_get_contents('php://input');
$json_array = json_decode(
    json: $input,
    associative: true,
    flags: JSON_THROW_ON_ERROR
);

echo 'Received JSON data: ';
print_r($json_array);
?>