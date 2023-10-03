<?php
$alpha->bravo(fn($charlie) => [
	'short_key' => 'value',
	'very_long_key' => $charlie === SOME_CONST
		? $delta
		: $echo,
	'key' => 'value2'
]);
$foxtrot->golf(fn() => [
	'key1' => $value1,
	'key_two' => $value2
]);

$alpha
	->bravo(fn($charlie) => [
		'short_key' => 'value',
		'very_long_key' => $charlie === SOME_CONST
			? $delta
			: $echo,
		'key' => 'value2'
	])
	->golf(fn() => [
		'key1' => $value1,
		'key_two' => $value2
	]);

$alpha->bravo(fn($charlie) => ['short_key' => 'value',
	'very_long_key' => $charlie === SOME_CONST
		? $delta
		: $echo,
	'key' => 'value2']);
$foxtrot->golf(fn() => ['key1' => $value1,
	'key_two' => $value2]);

$abc = a($b,
	$c);
$d = a($b, $c);
