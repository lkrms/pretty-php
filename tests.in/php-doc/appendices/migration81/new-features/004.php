<?php
$h = hash("xxh3", $data, options: ["seed" => 42]);
echo $h, "\n";
?>