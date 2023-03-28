<?php
class C {}

function f(?C $c) {
    var_dump($c);
}

f(new C);
f(null);
?>