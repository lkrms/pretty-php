<?php
$x = 4;
// this line might result in unexpected output:
echo "x minus one equals " . $x-1 . ", or so I hope\n";

// the desired precedence can be enforced by using parentheses:
echo "x minus one equals " . ($x-1) . ", or so I hope\n";

// this is not allowed, and throws a TypeError:
echo (("x minus one equals " . $x) - 1) . ", or so I hope\n";
?>