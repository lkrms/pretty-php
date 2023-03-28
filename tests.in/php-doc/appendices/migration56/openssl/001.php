<?php

// Requiring TLS 1.0 or better when using file_get_contents():
$ctx = stream_context_create([
    'ssl' => [
        'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
    ],
]);
$html = file_get_contents('https://google.com/', false, $ctx);

// Requiring TLS 1.1 or 1.2:
$ctx = stream_context_create([
    'ssl' => [
        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT |
                           STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
    ],
]);
$html = file_get_contents('https://google.com/', false, $ctx);

// Connecting using the tlsv1.2:// stream socket transport.
$sock = stream_socket_client('tlsv1.2://google.com:443/');

?>