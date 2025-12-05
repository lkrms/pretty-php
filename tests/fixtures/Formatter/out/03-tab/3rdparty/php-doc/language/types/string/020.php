<?php
const DATA_KEY = 'const-key';

$great = 'fantastic';
$arr = [
	'1',
	'2',
	'3',
	[41, 42, 43],
	'key' => 'Indexed value',
	'const-key' => 'Key with minus sign',
	'foo' => ['foo1', 'foo2', 'foo3']
];

// Won't work, outputs: This is { fantastic}
echo "This is { $great}";

// Works, outputs: This is fantastic
echo "This is {$great}";

class Square
{
	public $width;

	public function __construct(int $width)
	{
		$this->width = $width;
	}
}

$square = new Square(5);

// Works
echo "This square is {$square->width}00 centimeters wide.";

// Works, quoted keys only work using the curly brace syntax
echo "This works: {$arr['key']}";

// Works
echo "This works: {$arr[3][2]}";

echo "This works: {$arr[DATA_KEY]}";

// When using multidimensional arrays, always use braces around arrays
// when inside of strings
echo "This works: {$arr['foo'][2]}";

echo "This works: {$obj->values[3]->name}";

echo "This works: {$obj->$staticProp}";

// Won't work, outputs: C:\directory\{fantastic}.txt
echo "C:\directory\{$great}.txt";

// Works, outputs: C:\directory\fantastic.txt
echo "C:\\directory\\{$great}.txt";
?>