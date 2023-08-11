<?php

enum Suit: string
{
    case Hearts   = 'H';
    case Diamonds = 'D';
    case Spades   = 'S';
    case Clubs    = 'C';

    const Wild = self::Spades;
}
