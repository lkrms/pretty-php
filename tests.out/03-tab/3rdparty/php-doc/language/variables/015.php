<?php

function &get_instance_ref()
{
	static $obj;

	echo 'Static object: ';
	var_dump($obj);
	if (!isset($obj)) {
		$new = new stdClass;
		// Assign a reference to the static variable
		$obj = &$new;
	}
	if (!isset($obj->property)) {
		$obj->property = 1;
	} else {
		$obj->property++;
	}
	return $obj;
}

function &get_instance_noref()
{
	static $obj;

	echo 'Static object: ';
	var_dump($obj);
	if (!isset($obj)) {
		$new = new stdClass;
		// Assign the object to the static variable
		$obj = $new;
	}
	if (!isset($obj->property)) {
		$obj->property = 1;
	} else {
		$obj->property++;
	}
	return $obj;
}

$obj1 = get_instance_ref();
$still_obj1 = get_instance_ref();
echo "\n";
$obj2 = get_instance_noref();
$still_obj2 = get_instance_noref();
?>