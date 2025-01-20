<?php

// This is legal.
list(($a), ((($b)))) = $x;
// This is illegal, but not a syntax error.
list(1 + 1)          = $x;
