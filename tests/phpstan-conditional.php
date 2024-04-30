<?php declare(strict_types=1);

return [
    'includes' => [
        sprintf('../phpstan-baseline-%d.%d.neon', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION),
    ],
    'parameters' => [
        'phpVersion' => \PHP_VERSION_ID,
        'tmpDir' => sprintf('build/cache/phpstan/%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION),
    ],
];
