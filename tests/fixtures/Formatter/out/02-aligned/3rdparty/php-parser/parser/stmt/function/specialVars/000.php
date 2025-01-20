<?php

function a()
{
    global $a, ${'b'}, $$c;
    static $c, $d = 'e';
}
