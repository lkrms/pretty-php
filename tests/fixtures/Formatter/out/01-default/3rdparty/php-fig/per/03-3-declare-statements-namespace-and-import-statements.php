<?php

/**
 * This file contains an example of coding styles.
 */

declare(strict_types=1);

namespace Vendor\Package;

use SomeVendor\Pack\ANamespace\SubNamespace\ClassF;
use Vendor\Package\AnotherNamespace\ClassE as E;
use Vendor\Package\SomeNamespace\ClassD as D;
use Vendor\Package\{ClassA as A, ClassB, ClassC as C};

use function Another\Vendor\functionD;
use function Vendor\Package\{functionA, functionB, functionC};

use const Another\Vendor\CONSTANT_D;
use const Vendor\Package\{CONSTANT_A, CONSTANT_B, CONSTANT_C};

/**
 * FooBar is an example class.
 */
class FooBar
{
    // ...
}
