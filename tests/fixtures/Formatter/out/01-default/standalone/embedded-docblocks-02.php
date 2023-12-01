<?php
$foo = $bar || /*
                * comment
                */ $baz;

if ($foo || /*
             * comment
             */ $bar) {
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

$foo = $bar || /*
                * line 1
                * line 2
                */ $baz;

if ($foo || /*
             * line 1
             * line 2
             */ $bar) {
}

function baz(
    /*
     * line 1
     * line 2
     */
    $foo,

    /*
     * line 1
     * line 2
     */
    $bar

    /*
     * line 1
     * line 2
     */
) {}

class Foo
{
    /*
     * line 1
     * line 2
     */
    use Bar;
}
