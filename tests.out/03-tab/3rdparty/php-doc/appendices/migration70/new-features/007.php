<?php
class A
{
	private $x = 1;
}

// Pre PHP 7 code
$getX = function () {
	return $this->x;
};
$getXCB = $getX->bindTo(new A, 'A');  // intermediate closure
echo $getXCB();

// PHP 7+ code
$getX = function () {
	return $this->x;
};
echo $getX->call(new A);
