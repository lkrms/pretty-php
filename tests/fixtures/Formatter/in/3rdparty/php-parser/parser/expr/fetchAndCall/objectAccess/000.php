<?php

// property fetch variations
$a->b;
$a->b['c'];

// method call variations
$a->b();
$a->{'b'}();
$a->$b();
$a->$b['c']();

// array dereferencing
$a->b()['c'];