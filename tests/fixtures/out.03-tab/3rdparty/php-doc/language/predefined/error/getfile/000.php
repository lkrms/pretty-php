<?php
try {
	throw new Error;
} catch (Error $e) {
	echo $e->getFile();
}
?>