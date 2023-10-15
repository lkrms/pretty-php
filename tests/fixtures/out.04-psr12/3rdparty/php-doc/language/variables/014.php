<?php

function test_global_ref()
{
    global $obj;

    $new = new stdClass;
    $obj = &$new;
}

function test_global_noref()
{
    global $obj;

    $new = new stdClass;
    $obj = $new;
}

test_global_ref();
var_dump($obj);
test_global_noref();
var_dump($obj);
?>