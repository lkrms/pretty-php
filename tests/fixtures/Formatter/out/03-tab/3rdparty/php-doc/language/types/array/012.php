<?php
$source_array = [
	[1, 'John'],
	[2, 'Jane'],
];

foreach ($source_array as [$id, $name]) {
	echo "{$id}: '{$name}'\n";
}
?>