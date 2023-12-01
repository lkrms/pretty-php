<?php
function &collector()
{
	static $collection = array();

	return $collection;
}

array_push(collector(), 'foo');
?>