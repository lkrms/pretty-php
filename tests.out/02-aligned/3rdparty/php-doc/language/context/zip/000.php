<?php
// Read encrypted archive
$opts = array(
    'zip' => array(
        'password' => 'secret',
    ),
);
// create the context...
$context = stream_context_create($opts);

// ...and use it to fetch the data
echo file_get_contents('zip://test.zip#test.txt', false, $context);

?>