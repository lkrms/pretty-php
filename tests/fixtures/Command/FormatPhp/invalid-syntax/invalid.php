<?php
if ($a > $b):
    echo $a . ' is greater than ' . $b;
else if ($a == $b):
    echo 'The above line causes a parse error.';
endif;
