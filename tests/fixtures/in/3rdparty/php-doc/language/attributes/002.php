<?php

#[Attribute]
class MyAttribute
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

#[MyAttribute(value: 1234)]
class Thing
{
}

function dumpAttributeData($reflection) {
    $attributes = $reflection->getAttributes();

    foreach ($attributes as $attribute) {
       var_dump($attribute->getName());
       var_dump($attribute->getArguments());
       var_dump($attribute->newInstance());
    }
}

dumpAttributeData(new ReflectionClass(Thing::class));
/*
string(11) "MyAttribute"
array(1) {
  ["value"]=>
  int(1234)
}
object(MyAttribute)#3 (1) {
  ["value"]=>
  int(1234)
}
*/