<?php

function gen()
{
    // statements
    yield;
    yield $value;
    yield $key => $value;

    // expressions
    $data = yield;
    $data = (yield $value);
    $data = (yield $key => $value);

    // yield in language constructs with their own parentheses
    if (yield $foo);
    elseif (yield $foo);
    if (yield $foo):
    elseif (yield $foo):
    endif;
    while (yield $foo);
    do {
    } while (yield $foo);
    switch (yield $foo) {
    }
    die(yield $foo);

    // yield in function calls
    func(yield $foo);
    $foo->func(yield $foo);
    new Foo(yield $foo);

    yield from $foo;
    yield from $foo and yield from $bar;
    yield from $foo + $bar;
}
