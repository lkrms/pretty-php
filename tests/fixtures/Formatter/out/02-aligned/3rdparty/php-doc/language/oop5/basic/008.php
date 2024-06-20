<?php

class Test
{
    public static function getNew()
    {
        return new static();
    }
}

class Child extends Test {}

$obj1 = new Test();   // By the class name
$obj2 = new $obj1();  // Through the variable containing an object
var_dump($obj1 !== $obj2);

$obj3 = Test::getNew();  // By the class method
var_dump($obj3 instanceof Test);

$obj4 = Child::getNew();  // Through a child class method
var_dump($obj4 instanceof Child);

?>