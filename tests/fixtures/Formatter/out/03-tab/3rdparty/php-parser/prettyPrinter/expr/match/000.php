<?php

echo match (1) {
	0, 1 => 'Foo',
	// Comment
	2 => 'Bar',
	default => 'Foo',
};
