<?php
enum Suit: string
{
	case Hearts = 'H';
	case Diamonds = 'D';
	case Clubs = 'C';
	case Spades = 'S';
}

$h = Suit::tryFrom('H');

var_dump($h);

$b = Suit::tryFrom('B') ?? Suit::Spades;

var_dump($b);
?>