<?php
$juice = "apple";

echo "He drank some $juice juice.".PHP_EOL;
// Unintended. "s" is a valid character for a variable name, so this refers to $juices, not $juice.
echo "He drank some juice made of $juices.";
// Explicitly specify the end of the variable name by enclosing the reference in braces.
echo "He drank some juice made of {$juice}s.";
?>