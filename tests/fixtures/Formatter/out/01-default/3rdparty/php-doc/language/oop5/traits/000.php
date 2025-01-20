<?php

trait TraitA
{
    public function sayHello()
    {
        echo 'Hello';
    }
}

trait TraitB
{
    public function sayWorld()
    {
        echo 'World';
    }
}

class MyHelloWorld
{
    use TraitA, TraitB;  // A class can use multiple traits

    public function sayHelloWorld()
    {
        $this->sayHello();
        echo ' ';
        $this->sayWorld();
        echo "!\n";
    }
}

$myHelloWorld = new MyHelloWorld();
$myHelloWorld->sayHelloWorld();

?>