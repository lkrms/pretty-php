<?php

class Foo
{
    public $value = 42;

    public function &getValue()
    {
        return $this->value;
    }
}

$obj        = new Foo();
$myValue    = &$obj->getValue();  // $myValue is a reference to $obj->value, which is 42
$obj->value = 2;
echo $myValue;                    // Prints the new value of $obj->value, i.e. 2

?>