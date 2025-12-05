<?php

function generator(): Generator
{
    echo "I'm a generator!\n";

    for ($i = 1; $i <= 3; $i++) {
        yield $i;
    }
}

// Initialize the generator
$generator = generator();

// Rewind the generator to the beginning of the first yield expression,
// if it's not already there
$generator->rewind();  // I'm a generator!

// Nothing happens here; the generator is already rewound
$generator->rewind();  // No output (NULL)

// This rewinds the generator to the first yield expression,
// if it's not already there, and iterates over the generator
foreach ($generator as $value) {
    // After yielding the first value, the generator remains at
    // the first yield expression until it resumes execution and advances to the next yield
    echo $value, PHP_EOL;  // 1

    break;
}

// Resume and rewind again. No error occurs because the generator has not advanced beyond the first yield
$generator->rewind();

echo $generator->current(), PHP_EOL;  // 1

// No error occurs, the generator is still at the first yield
$generator->rewind();

// This advances the generator to the second yield expression
$generator->next();

try {
    // This will throw an Exception,
    // because the generator has already advanced to the second yield
    $generator->rewind();  // Fatal error: Uncaught Exception: Cannot rewind a generator that was already run
} catch (Exception $e) {
    echo $e->getMessage();
}

?>