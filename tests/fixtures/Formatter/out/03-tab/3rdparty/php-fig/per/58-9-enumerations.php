<?php

enum Suit: string
{
	case Hearts = 'H';
	case Diamonds = 'D';
	case Spades = 'S';
	case Clubs = 'C';

	public const Wild = self::Spades;
}
