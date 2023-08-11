<?php

class Dog extends Animal
{
    public function eat(Food $food) {
        echo $this->name . " eats " . get_class($food);
    }
}