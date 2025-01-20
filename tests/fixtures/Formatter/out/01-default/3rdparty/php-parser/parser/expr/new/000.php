<?php

new A;
new A($b);

// class name variations
new $a();
new $a['b']();
new A::$b();
// DNCR object access
new $a->b();
new $a->b->c();
new $a->b['c']();

// test regression introduces by new dereferencing syntax
(new A);
