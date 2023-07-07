vars.php
<?php

$color = 'green';
$fruit = 'apple';

?>

test.php
<?php

echo "A $color $fruit";  // A

include 'vars.php';

echo "A $color $fruit";  // A green apple

?>