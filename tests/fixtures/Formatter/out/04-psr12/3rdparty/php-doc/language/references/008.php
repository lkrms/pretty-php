<?php

function foo(&$var)
{
    $var = &$GLOBALS['baz'];
}

foo($bar);
?>