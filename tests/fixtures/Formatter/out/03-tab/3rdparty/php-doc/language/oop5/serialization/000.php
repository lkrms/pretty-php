<?php
// A.php:

class A
{
	public $one = 1;

	public function show_one()
	{
		echo $this->one;
	}
}

// page1.php:

include 'A.php';

$a = new A;
$s = serialize($a);
// store $s somewhere where page2.php can find it.
file_put_contents('store', $s);

// page2.php:

// this is needed for the unserialize to work properly.
include 'A.php';

$s = file_get_contents('store');
$a = unserialize($s);

// now use the function show_one() of the $a object.
$a->show_one();
?>