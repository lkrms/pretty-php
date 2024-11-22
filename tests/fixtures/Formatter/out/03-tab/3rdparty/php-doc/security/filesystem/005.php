<?php

$file = $_GET['file'];

// Whitelisting possible values
switch ($file) {
	case 'main':
	case 'foo':
	case 'bar':
		include '/home/wwwrun/include/' . $file . '.php';
		break;
	default:
		include '/home/wwwrun/include/main.php';
}

?>