<?php
$result = match ($a) {
	'foo' => 'Foo',
	'bar' => 'Bar',
	default => 'Baz',
};
