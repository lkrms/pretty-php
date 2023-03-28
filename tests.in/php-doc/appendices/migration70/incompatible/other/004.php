<?php
function foo($x) {
    $x++;
    var_dump(func_get_arg(0));
}
foo(1);?>