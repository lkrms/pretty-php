<?php

$a;
$$a;
${$a};
$a->b;
$a->b();
$a->b($c);
$a->$b();
$a->{$b}();
$a->$b[$c]();
$$a->b;
$a[$b];
$a[$b]();
$$a[$b];
$a::B;
$a::$b;
$a::b();
$a::b($c);
$a::$b();
$a::$b[$c];
$a::$b[$c]($d);
$a::{$b[$c]}($d);
$a::{$b->c}();
A::$$b[$c]();
a();
$a();
$a()[$b];
$a->b()[$c];
$a::$b()[$c];
(new A)->b;
(new A())->b();
(new $$a)[$b];
(new $a->b)->c;

global $a, $$a;