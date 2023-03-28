<?php
function makeyogurt($container = "bowl", $flavour)
{
    return "Making a $container of $flavour yogurt.\n";
}
 
echo makeyogurt("raspberry"); // "raspberry" is $container, not $flavour
?>