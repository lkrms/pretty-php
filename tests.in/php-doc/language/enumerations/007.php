<?php
$record = get_stuff_from_database($id);
print $record['suit'];

$suit =  Suit::from($record['suit']);
// Invalid data throws a ValueError: "X" is not a valid scalar value for enum "Suit"
print $suit->value;

$suit = Suit::tryFrom('A') ?? Suit::Spades;
// Invalid data returns null, so Suit::Spades is used instead.
print $suit->value;
?>