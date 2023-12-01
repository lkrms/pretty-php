<?php
$h = hash('murmur3f', $data, options: ['seed' => 42]);
echo $h, "\n";
?>