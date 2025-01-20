<?php

use A\B;
use C\D as E;
use F\G as H, J;

// evil alias notation - Do Not Use!
use \A;
use \A as B;

// function and constant aliases
use function foo\bar;
use function foo\bar as baz;
use const foo\BAR;
use const foo\BAR as BAZ;