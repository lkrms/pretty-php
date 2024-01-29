<?php

interface Colorful
{
	public function color(): string;
}

enum Suit implements Colorful
{
	case Hearts;
	case Diamonds;
	case Clubs;
	case Spades;

	// Fulfills the interface contract.
	public function color(): string
	{
		return match ($this) {
			Suit::Hearts, Suit::Diamonds => 'Red',
			Suit::Clubs, Suit::Spades => 'Black',
		};
	}

	// Not part of an interface; that's fine.
	public function shape(): string
	{
		return 'Rectangle';
	}
}

function paint(Colorful $c)
{
	/* ... */
}

paint(Suit::Clubs);  // Works

print Suit::Diamonds->shape();  // prints "Rectangle"
?>