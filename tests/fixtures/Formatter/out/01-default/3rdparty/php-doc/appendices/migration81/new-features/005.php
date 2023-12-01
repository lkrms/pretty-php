<?php
$h = hash('xxh3', $data, options: ['secret' => 'at least 136 bytes long secret here']);
echo $h, "\n";
?>