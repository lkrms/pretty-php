<?php
$pkey = openssl_pkey_new();
openssl_pkey_export($pkey, 'secret passphrase');

$spkac = openssl_spki_new($pkey, 'challenge string');
?>