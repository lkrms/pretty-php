<?php

list() = $a;
list($a) = $b;
list($a, $b, $c) = $d;
list(, $a) = $b;
list(,, $a,, $b) = $c;
list(list($a)) = $b;
list(, list(, list(, $a), $b)) = $c;
