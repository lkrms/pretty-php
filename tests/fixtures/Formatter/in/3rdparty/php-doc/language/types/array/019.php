<?php
// Show all errors
error_reporting(E_ALL);

$arr = array('fruit' => 'apple', 'veggie' => 'carrot');

// Correct
echo $arr['fruit'], PHP_EOL;  // apple
echo $arr['veggie'], PHP_EOL; // carrot

// Incorrect. This does not work and throws a PHP Error because
// of an undefined constant named fruit
//
// Error: Undefined constant "fruit"
try {
    echo $arr[fruit];
} catch (Error $e) {
    echo get_class($e), ': ', $e->getMessage(), PHP_EOL;
}

// This defines a constant to demonstrate what's going on.  The value 'veggie'
// is assigned to a constant named fruit.
define('fruit', 'veggie');

// Notice the difference now
echo $arr['fruit'], PHP_EOL;  // apple
echo $arr[fruit], PHP_EOL;    // carrot

// The following is okay, as it's inside a string. Constants are not looked for
// within strings, so no error occurs here
echo "Hello $arr[fruit]", PHP_EOL;      // Hello apple

// With one exception: braces surrounding arrays within strings allows constants
// to be interpreted
echo "Hello {$arr[fruit]}", PHP_EOL;    // Hello carrot
echo "Hello {$arr['fruit']}", PHP_EOL;  // Hello apple

// Concatenation is another option
echo "Hello " . $arr['fruit'], PHP_EOL; // Hello apple
?>