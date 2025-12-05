<?php
function exampleFunction($input)
{
	$result = (static function () use ($input) {
		static $counter = 0;
		$counter++;
		return "Input: $input, Counter: $counter\n";
	});

	return $result();
}

// Calls to exampleFunction will recreate the anonymous function, so the static
// variable does not retain its value.
echo exampleFunction('A');  // Outputs: Input: A, Counter: 1
echo exampleFunction('B');  // Outputs: Input: B, Counter: 1
?>