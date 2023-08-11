<?php
$stream = fopen('rar://'
        . rawurlencode(dirname(__FILE__)) . DIRECTORY_SEPARATOR
        . 'encrypted_headers.rar' . '#encfile1.txt', 'r', false,
    stream_context_create(
        array(
            'rar' =>
                array(
                    'open_password' => 'samplepassword'
                )
        )
    ));
var_dump(stream_get_contents($stream));
/* creation and last access date is opt-in in WinRAR, hence most
 * files don't have them */
var_dump(fstat($stream));
?>