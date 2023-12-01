<?php

// object to array
$obj = new class {
	public function __construct()
	{
		$this->{0} = 1;
	}
};
$arr = (array) $obj;
var_dump(
	$arr,
	$arr[0],  // now accessible
	$arr['0']  // now accessible
);
