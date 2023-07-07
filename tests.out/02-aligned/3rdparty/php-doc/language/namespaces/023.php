<?php

use some\namespace\ClassA;
use some\namespace\ClassB;
use some\namespace\ClassC as C;

use const some\namespace\ConstA;
use const some\namespace\ConstB;
use const some\namespace\ConstC;

use function some\namespace\fn_a;
use function some\namespace\fn_b;
use function some\namespace\fn_c;

// is equivalent to the following groupped use declaration
use some\namespace\{ClassA, ClassB, ClassC as C};

use const some\namespace\{ConstA, ConstB, ConstC};

use function some\namespace\{fn_a, fn_b, fn_c};
