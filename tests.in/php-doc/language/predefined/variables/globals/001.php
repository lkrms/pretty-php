<?php
 // Generates compile-time error:
 $GLOBALS = [];
 $GLOBALS += [];
 $GLOBALS =& $x;
 $x =& $GLOBALS;
 unset($GLOBALS);
 array_pop($GLOBALS);
 // ...and any other write/read-write operation on $GLOBALS
 ?>