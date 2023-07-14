<?php
$array = [0];
foreach ($array as &$val) {
	var_dump($val);
	$array[1] = 1;
}
?>