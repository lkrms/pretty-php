<?php

$kitty = (new CatShelter)->adopt('Ricky');
$catFood = new AnimalFood();
$kitty->eat($catFood);
echo "\n";

$doggy = (new DogShelter)->adopt('Mavrick');
$banana = new Food();
$doggy->eat($banana);
