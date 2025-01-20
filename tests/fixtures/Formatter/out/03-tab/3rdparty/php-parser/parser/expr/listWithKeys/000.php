<?php

list('a' => $b) = ['a' => 'b'];
list('a' => list($b => $c), 'd' => $e) = $x;
