<?php
interface A
{
    const B = 'Interface constant';
}

// Prints: Interface constant
echo A::B;


class B implements A
{
    const B = 'Class constant';
}

// Prints: Class constant
// Prior to PHP 8.1.0, this will however not work because it was not
// allowed to override constants.
echo B::B;
?>