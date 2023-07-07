<?php
function &collector()
{
    static $collection = array();

    return $collection;
}

$collection = &collector();
$collection[] = 'foo';
?>