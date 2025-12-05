<?php
// Using anonymous function syntax
$double1 = function ($a) {
    return $a * 2;
};

// Using first-class callable syntax
function double_function($a) {
    return $a * 2;
}
$double2 = double_function(...);

// Using arrow function syntax
$double3 = fn($a) => $a * 2;

// Using Closure::fromCallable
$double4 = Closure::fromCallable('double_function');

// Use the closure as a callback here to
// double the size of each element in our range
$new_numbers = array_map($double1, range(1, 5));
print implode(' ', $new_numbers) . PHP_EOL;

$new_numbers = array_map($double2, range(1, 5));
print implode(' ', $new_numbers) . PHP_EOL;

$new_numbers = array_map($double3, range(1, 5));
print implode(' ', $new_numbers) . PHP_EOL;

$new_numbers = array_map($double4, range(1, 5));
print implode(' ', $new_numbers);

?>