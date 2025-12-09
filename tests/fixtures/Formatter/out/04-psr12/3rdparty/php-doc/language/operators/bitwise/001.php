<?php
echo 12 ^ 9, PHP_EOL;  // Outputs '5'

echo '12' ^ '9', PHP_EOL;  // Outputs the Backspace character (ascii 8)
// ('1' (ascii 49)) ^ ('9' (ascii 57)) = #8

echo 'hallo' ^ 'hello', PHP_EOL;  // Outputs the ascii values #0 #4 #0 #0 #0
// 'a' ^ 'e' = #4

echo 2 ^ '3', PHP_EOL;  // Outputs 1
// 2 ^ ((int) "3") == 1

echo '2' ^ 3, PHP_EOL;  // Outputs 1
// ((int) "2") ^ 3 == 1
?>