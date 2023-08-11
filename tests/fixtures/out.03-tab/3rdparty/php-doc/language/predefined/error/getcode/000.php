<?php
try {
	throw new Error('Some error message', 30);
} catch (Error $e) {
	echo 'The Error code is: ' . $e->getCode();
}
?>