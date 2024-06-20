<?php
class DefaultCoffeeMaker {
    public function brew() {
        return "Making coffee.\n";
    }
}
class FancyCoffeeMaker {
    public function brew() {
        return "Crafting a beautiful coffee just for you.\n";
    }
}
function makecoffee($coffeeMaker = new DefaultCoffeeMaker)
{
    return $coffeeMaker->brew();
}
echo makecoffee();
echo makecoffee(new FancyCoffeeMaker);
?>