<?php
class Animal {}
class Dog extends Animal {}
class Poodle extends Dog {}

interface PetOwner
{
    // Only a get operation is required, so this may be covariant.
    public Animal $pet { get; }
}

class DogOwner implements PetOwner
{
    // This may be a more restrictive type since the "get" side
    // still returns an Animal.  However, as a native property
    // children of this class may not change the type anymore.
    public Dog $pet;
}

class PoodleOwner extends DogOwner
{
    // This is NOT ALLOWED, because DogOwner::$pet has both
    // get and set operations defined and required.
    public Poodle $pet;
}
?>