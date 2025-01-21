<?php

switch ($a) {
	case 0:
		break;
	// Comment
	case 1;
	default:
}

// alternative syntax
switch ($a):
endswitch;

// leading semicolon
switch ($a) {
		;
}
switch ($a):
		;
endswitch;
