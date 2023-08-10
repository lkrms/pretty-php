#!/usr/bin/env php
<?php declare(strict_types=1);

$args = $argv;
array_shift($args);

for ($i = 0; $i < count($args); $i += 2) {
    if ($args[$i] === 'assets') {
        $i++;
        $manifest['assets'][] = [
            'type' => $args[$i],
            'path' => $args[$i + 1],
        ];
        continue;
    }
    $manifest[$args[$i]] = $args[$i + 1];
}

printf("%s\n", json_encode($manifest ?? [], JSON_PRETTY_PRINT));