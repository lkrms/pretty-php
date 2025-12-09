<?php

class Foo
{
    // These are all acceptable.
    public private(set) string $one;
    private(set) string $two;
    readonly string $three;

    // These are not.
    private(set) public string $four;
}
