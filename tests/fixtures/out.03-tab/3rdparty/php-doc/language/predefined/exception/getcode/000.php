<?php
try {
	throw new Exception('Some error message', 30);
} catch (Exception $e) {
	echo 'The exception code is: ' . $e->getCode();
}
?>