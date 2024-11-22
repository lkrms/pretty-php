<?php

function foo($a = [], $b) {}     // Default not used; deprecated as of PHP 8.0.0
function foo($a, $b) {}          // Functionally equivalent, no deprecation notice

function bar(A $a = null, $b) {} // As of PHP 8.1.0, $a is implicitly required
                                 // (because it comes before the required one),
                                 // but implicitly nullable (deprecated as of PHP 8.4.0),
                                 // because the default parameter value is null
function bar(?A $a, $b) {}       // Recommended

?>