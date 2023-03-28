<?php
$session = ssh2_connect('example.com', 22);
ssh2_auth_pubkey_file($session, 'username', '/home/username/.ssh/id_rsa.pub',
    '/home/username/.ssh/id_rsa', 'secret');
$stream = fopen("ssh2.tunnel://$session/remote.example.com:1234", 'r');
?>