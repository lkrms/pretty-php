<?php
 function foo($a = [], $b) {} // Default not used; deprecated as of PHP 8.0.0
 function foo($a, $b) {}      // Functionally equivalent, no deprecation notice

 function bar(A $a = null, $b) {} // Still allowed; $a is required but nullable
 function bar(?A $a, $b) {}       // Recommended
 ?>