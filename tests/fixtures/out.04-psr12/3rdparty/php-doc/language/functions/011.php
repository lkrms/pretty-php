<?php

function makeyogurt($flavour, $container = 'bowl')
{
    return "Making a $container of $flavour yogurt.\n";
}

echo makeyogurt('raspberry');  // "raspberry" is $flavour
?>