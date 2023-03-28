<?php
$ctx = stream_context_create(['ssl' => [
    'capture_session_meta' => TRUE
]]);
 
$html = file_get_contents('https://google.com/', FALSE, $ctx);
$meta = stream_context_get_options($ctx)['ssl']['session_meta'];
var_dump($meta);
?>