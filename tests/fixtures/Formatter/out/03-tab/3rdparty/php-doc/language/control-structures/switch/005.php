<?php
$offset = 1;
$start = 3;

switch (true) {
	case $start - $offset === 1:
		print 'A';
		break;
	case $start - $offset === 2:
		print 'B';
		break;
	case $start - $offset === 3:
		print 'C';
		break;
	case $start - $offset === 4:
		print 'D';
		break;
}

// Prints "B"
?>