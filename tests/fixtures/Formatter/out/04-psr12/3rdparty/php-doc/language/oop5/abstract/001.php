<?php

abstract class AbstractClass
{
    // An abstract method only needs to define the required arguments
    abstract protected function prefixName($name);
}

class ConcreteClass extends AbstractClass
{
    // A child class may define optional parameters which are not present in the parent's signature
    public function prefixName($name, $separator = '.')
    {
        if ($name == 'Pacman') {
            $prefix = 'Mr';
        } elseif ($name == 'Pacwoman') {
            $prefix = 'Mrs';
        } else {
            $prefix = '';
        }

        return "{$prefix}{$separator} {$name}";
    }
}

$class = new ConcreteClass();
echo $class->prefixName('Pacman'), "\n";
echo $class->prefixName('Pacwoman'), "\n";

?>