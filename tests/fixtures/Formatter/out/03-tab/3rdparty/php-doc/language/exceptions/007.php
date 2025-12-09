<?php

function test()
{
	do_something_risky() or throw new Exception('It did not work');
}

function do_something_risky()
{
	return false;  // Simulate failure
}

try {
	test();
} catch (Exception $e) {
	print $e->getMessage();
}
?>