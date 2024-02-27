<?php declare(strict_types=1);

return [
    'parameters' => [
        'tmpDir' => sprintf('build/cache/phpstan/%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION),
    ],
];
