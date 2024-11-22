<?php

class Book
{
    protected string $title;
    public protected(set) string $author;
    protected private(set) int $pubYear;
}

class SpecialBook extends Book
{
    public protected(set) $title;  // OK, as reading is wider and writing the same.
    public string $author;  // OK, as reading is the same and writing is wider.
    public protected(set) int $pubYear;  // Fatal Error. private(set) properties are final.
}
?>