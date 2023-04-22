<?php

class A
{
    private $A;  // This will become '\0A\0A'
}

class B extends A
{
    private $A;  // This will become '\0B\0A'
    public $AA;  // This will become 'AA'
}

var_dump((array) new B());
?>