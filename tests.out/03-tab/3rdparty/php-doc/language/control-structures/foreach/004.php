<?php
$array = [
	[1, 2],
	[3, 4],
];

foreach ($array as list($a, $b)) {
	// $a contains the first element of the nested array,
	// and $b contains the second element.
	echo "A: $a; B: $b\n";
}
?>