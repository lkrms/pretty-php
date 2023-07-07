<?php
$pkey = openssl_pkey_new();
openssl_pkey_export($pkey, 'secret passphrase');

$spkac     = openssl_spki_new($pkey, 'challenge string');
$challenge = openssl_spki_export_challenge($spkac);
echo $challenge;
?>