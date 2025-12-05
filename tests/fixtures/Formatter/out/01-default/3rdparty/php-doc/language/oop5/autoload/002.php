<?php
require __DIR__ . '/vendor/autoload.php';

$uuid = Ramsey\Uuid\Uuid::uuid7();

echo 'Generated new UUID -> ', $uuid->toString(), "\n";
?>