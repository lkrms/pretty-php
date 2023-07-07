<?php
namespace bar;

$a = FOO;       // produces notice - undefined constants "FOO" assumed "FOO";
$a = \FOO;      // fatal error, undefined namespace constant FOO
$a = Bar\FOO;   // fatal error, undefined namespace constant bar\Bar\FOO
$a = \Bar\FOO;  // fatal error, undefined namespace constant Bar\FOO
?>