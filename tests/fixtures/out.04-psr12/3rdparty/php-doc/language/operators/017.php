<?php
echo 0 ?: 1 ?: 2 ?: 3, PHP_EOL;  // 1
echo 0 ?: 0 ?: 2 ?: 3, PHP_EOL;  // 2
echo 0 ?: 0 ?: 0 ?: 3, PHP_EOL;  // 3
?>