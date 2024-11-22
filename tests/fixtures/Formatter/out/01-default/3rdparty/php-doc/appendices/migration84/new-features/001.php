<?php
class Example
{
    // The first visibility modifier controls the get-visibility, and the second modifier
    // controls the set-visibility. The get-visibility must not be narrower than set-visibility.
    public protected(set) string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
