<?php
var_dump((bool) '');         // bool(false)
var_dump((bool) '0');        // bool(false)
var_dump((bool) 1);          // bool(true)
var_dump((bool) -2);         // bool(true)
var_dump((bool) 'foo');      // bool(true)
var_dump((bool) 2.3e5);      // bool(true)
var_dump((bool) array(12));  // bool(true)
var_dump((bool) array());    // bool(false)
var_dump((bool) 'false');    // bool(true)
?>