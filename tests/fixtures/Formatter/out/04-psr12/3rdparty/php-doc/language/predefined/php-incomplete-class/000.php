<?php

class MyClass
{
    public string $property = 'myValue';
}

$myObject = new MyClass;

$foo = serialize($myObject);

// unserializes all objects into __PHP_Incomplete_Class objects
$disallowed = unserialize($foo, ['allowed_classes' => false]);

var_dump($disallowed);

// unserializes all objects into __PHP_Incomplete_Class objects except those of MyClass2 and MyClass3
$disallowed2 = unserialize($foo, ['allowed_classes' => ['MyClass2', 'MyClass3']]);

var_dump($disallowed2);

// unserializes undefined class into __PHP_Incomplete_Class object
$undefinedClass = unserialize('O:16:"MyUndefinedClass":0:{}');

var_dump($undefinedClass);
