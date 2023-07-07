<?php
$expected  = crypt('12345', '$2a$07$usesomesillystringforsalt$');
$correct   = crypt('12345', '$2a$07$usesomesillystringforsalt$');
$incorrect = crypt('1234', '$2a$07$usesomesillystringforsalt$');

var_dump(hash_equals($expected, $correct));
var_dump(hash_equals($expected, $incorrect));
?>