return.php
<?php

$var = 'PHP';

return $var;

?>

noreturn.php
<?php

$var = 'PHP';

?>

testreturns.php
<?php

$foo = include 'return.php';

echo $foo;  // prints 'PHP'

$bar = include 'noreturn.php';

echo $bar;  // prints 1

?>