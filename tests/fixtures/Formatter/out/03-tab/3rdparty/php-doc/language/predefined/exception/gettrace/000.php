<?php
function test()
{
	throw new Exception;
}

try {
	test();
} catch (Exception $e) {
	var_dump($e->getTrace());
}
?>