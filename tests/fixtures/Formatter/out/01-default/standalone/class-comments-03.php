<?php
class foo {}
class bar {}
class baz {}

/**
 * comment
 */
class foo
{
    function baz() {}
}

/**
 * comment
 */
class bar
{
    function baz() {}
}

/**
 * comment
 */
class qux {}  // qux
// continuation

/**
 * comment
 */
class foo
{
    function baz() {}
}  // foo
// not a continuation

/**
 * comment
 */
class bar
{
    function baz() {}
}
/** comment */

/**
 * comment
 */
class qux
{
    function foo() {}
    function bar() {}
    function baz() {}
}

class quux {}
class foo {}
