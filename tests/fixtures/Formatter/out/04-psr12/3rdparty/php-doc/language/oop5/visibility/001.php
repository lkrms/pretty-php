<?php

class Book
{
    public function __construct(
        public private(set) string $title,
        public protected(set) string $author,
        protected private(set) int $pubYear,
    ) {}
}

class SpecialBook extends Book
{
    public function update(string $author, int $year): void
    {
        $this->author = $author;  // OK
        $this->pubYear = $year;  // Fatal Error
    }
}

$b = new Book('How to PHP', 'Peter H. Peterson', 2024);

echo $b->title;  // Works
echo $b->author;  // Works
echo $b->pubYear;  // Fatal Error

$b->title = 'How not to PHP';  // Fatal Error
$b->author = 'Pedro H. Peterson';  // Fatal Error
$b->pubYear = 2023;  // Fatal Error
?>