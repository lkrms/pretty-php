<?php
function &returns_reference()
{
    return $someref;
}

$newref =& returns_reference();
?>