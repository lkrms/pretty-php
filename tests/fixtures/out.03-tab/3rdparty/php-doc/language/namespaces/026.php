<?php
namespace A\B\C;

const E_ERROR = 45;

function strlen($str)
{
	return \strlen($str) - 1;
}

echo E_ERROR, "\n";  // prints "45"
echo INI_ALL, "\n";  // prints "7" - falls back to global INI_ALL

echo strlen('hi'), "\n";  // prints "1"
if (is_array('hi')) {  // prints "is not array"
	echo "is array\n";
} else {
	echo "is not array\n";
}
?>