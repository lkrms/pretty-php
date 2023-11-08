<?php
$foo = $bar || /*
                * comment
                */ $baz;

if (
    $foo || /*
             * comment
             */ $bar
) {
}

function baz(
    /*
     * comment
     */
    $foo,

    /*
     * comment
     */
    $bar

    /*
     * comment
     */
) {}

class Foo
{
    /*
     * comment
     */
    use Bar;
}
