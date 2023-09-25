<?php
class foo {}
class bar {}
class baz {}

/** */
class foo
{
    function baz() {}
}

/** */
class bar
{
    function baz() {}
}

/** */
class qux {}  // qux
// continuation

/** */
class foo
{
    function baz() {}
}  // foo
// not a continuation

/** */
class bar
{
    function baz() {}
}

/** */

/** */
class qux
{
    function foo() {}
    function bar() {}
    function baz() {}
}

class quux {}

class foo {}
