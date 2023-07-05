<?php
var_dump(0 == 'a');
var_dump('1' == '01');
var_dump('10' == '1e1');
var_dump(100 == '1e2');

switch ('a') {
    case 0:
        echo '0';
        break;
    case 'a':
        echo 'a';
        break;
}
?>