<?php

// method name variations
A::b();
A::{'b'}();
A::$b();
A::$b['c']();
A::$b['c']['d']();

// array dereferencing
A::b()['c'];

// class name variations
static::b();
$a::b();
${'a'}::b();
$a['b']::c();
