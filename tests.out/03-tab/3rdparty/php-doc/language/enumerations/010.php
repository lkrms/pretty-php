<?php
interface Colorful
{
	public function color(): string;
}

final class Suit implements UnitEnum, Colorful
{
	public const Hearts = new self('Hearts');
	public const Diamonds = new self('Diamonds');
	public const Clubs = new self('Clubs');
	public const Spades = new self('Spades');

	private function __construct(public readonly string $name) {}

	public function color(): string
	{
		return match ($this) {
			Suit::Hearts, Suit::Diamonds => 'Red',
			Suit::Clubs, Suit::Spades => 'Black',
		};
	}

	public function shape(): string
	{
		return 'Rectangle';
	}

	public static function cases(): array
	{
		// Illegal method, because manually defining a cases() method on an Enum is disallowed.
		// See also "Value listing" section.
	}
}
?>