<?php

class Test
{
    public (A&B)|(X&Y) $prop;
    public readonly (A&B)|C $prop2;
}

function test((A&B)|(X&Y) $a): (A&B)|(X&Y) {}
