<?php
// Loop over all *.php files in ext/spl/examples/ directory
// and print the filename and its size
$it = new DirectoryIterator('glob://ext/spl/examples/*.php');
foreach ($it as $f) {
	printf("%s: %.1FK\n", $f->getFilename(), $f->getSize() / 1024);
}
?>