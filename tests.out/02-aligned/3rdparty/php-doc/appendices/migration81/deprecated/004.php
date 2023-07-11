<?php
// From undefined
$arr[]                 = 'some value';
$arr['doesNotExist'][] = 2;
// From null
$arr   = null;
$arr[] = 2;
?>