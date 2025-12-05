<?php

class Example
{
    public function __construct(
        private int $data
    ) {}

    // ...
}

$initializer = static function (Example $ghost): void {
    // Fetch data or dependencies
    $data = getData();
    // Initialize
    $ghost->__construct($data);
};

$reflector = new ReflectionClass(Example::class);
$object = $reflector->newLazyGhost($initializer);
