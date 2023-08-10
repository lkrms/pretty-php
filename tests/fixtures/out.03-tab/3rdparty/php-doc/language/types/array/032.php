<?php
// fill an array with all items from a directory
$handle = opendir('.');
while (false !== ($file = readdir($handle))) {
	$files[] = $file;
}
closedir($handle);
?>