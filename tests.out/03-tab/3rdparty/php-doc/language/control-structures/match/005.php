<?php
$condition = 5;

try {
	match ($condition) {
		1, 2 => foo(),
		3, 4 => bar(),
	};
} catch (\UnhandledMatchError $e) {
	var_dump($e);
}
?>