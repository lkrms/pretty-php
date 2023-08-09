<?php

class A
{
    public int $prop;
}

class B extends A
{
    // Illegal: read-write -> readonly
    public readonly int $prop;
}
?>