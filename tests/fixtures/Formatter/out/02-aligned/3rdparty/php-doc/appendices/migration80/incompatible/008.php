<?php
$ctx = stream_context_create(['http' => ['protocol_version' => '1.0']]);
echo file_get_contents('http://example.org', false, $ctx);
?>