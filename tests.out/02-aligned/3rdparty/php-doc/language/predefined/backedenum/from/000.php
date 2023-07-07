<?php
enum Suit: string
{
    case Hearts   = 'H';
    case Diamonds = 'D';
    case Clubs    = 'C';
    case Spades   = 'S';
}

$h = Suit::from('H');

var_dump($h);

$b = Suit::from('B');
?>