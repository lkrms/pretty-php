<?php
$a = 3;
$b = &$a;  // $b is a reference to $a

print "$a\n";  // prints 3
print "$b\n";  // prints 3

$a = 4;  // change $a

print "$a\n";  // prints 4
print "$b\n";  // prints 4 as well, since $b is a reference to $a, which has
// been changed
?>