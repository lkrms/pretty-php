<?php declare(strict_types=1);

$parameters = [
    'phpVersion' => \PHP_VERSION_ID,
    'tmpDir' => sprintf('build/cache/phpstan/%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION),
];

$dir = dirname(__DIR__);

if (\PHP_VERSION_ID < 80000) {
    return [
        'parameters' => [
            'ignoreErrors' => [
                [
                    'message' => '#^PHPDoc tag @var with type array\<string\> is not subtype of native type non\-empty\-list\<string\>\|false\.$#',
                    'count' => 1,
                    'path' => "$dir/src/Rule/SimplifyNumbers.php",
                ],
            ],
        ] + $parameters,
    ];
}

return [
    'parameters' => $parameters,
];
