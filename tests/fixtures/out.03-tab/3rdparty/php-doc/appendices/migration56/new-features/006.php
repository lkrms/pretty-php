<?php
$a = gmp_init(42);
$b = gmp_init(17);

if (version_compare(PHP_VERSION, '5.6', '<')) {
	echo gmp_intval(gmp_add($a, $b)), PHP_EOL;
	echo gmp_intval(gmp_add($a, 17)), PHP_EOL;
	echo gmp_intval(gmp_add(42, $b)), PHP_EOL;
} else {
	echo $a + $b, PHP_EOL;
	echo $a + 17, PHP_EOL;
	echo 42 + $b, PHP_EOL;
}
?>