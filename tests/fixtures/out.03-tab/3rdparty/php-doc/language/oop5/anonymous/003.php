<?php
function anonymous_class()
{
	return new class {};
}

if (get_class(anonymous_class()) === get_class(anonymous_class())) {
	echo 'same class';
} else {
	echo 'different class';
}
