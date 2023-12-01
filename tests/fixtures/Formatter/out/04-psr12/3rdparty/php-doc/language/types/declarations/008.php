<?php

class C {}

function getC(): C
{
    return new C;
}

var_dump(getC());
?>