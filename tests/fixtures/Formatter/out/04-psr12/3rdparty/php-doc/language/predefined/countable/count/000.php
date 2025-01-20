<?php

class Counter implements Countable
{
    private $count = 0;

    public function count(): int
    {
        return ++$this->count;
    }
}

$counter = new Counter;

for ($i = 0; $i < 10; ++$i) {
    echo 'I have been count()ed ' . count($counter) . " times\n";
}

?>