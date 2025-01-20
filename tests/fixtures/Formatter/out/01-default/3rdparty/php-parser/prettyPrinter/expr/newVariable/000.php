<?php
new ('a' . 'b');
new (x);
new (foo());
new ('foo');
new (x[0]);
new (x->y);
new ((x)::$y);
$x instanceof ('a' . 'b');
$x instanceof ($y++);
