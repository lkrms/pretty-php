<?php

class foo
{
    function baz()
    {
        quux();
    }  // baz
}

// foo
// continuation

class bar
{
    function baz()
    {
        quux();
    }  // baz
}

// bar
// continuation

class qux {}

// qux
// continuation
