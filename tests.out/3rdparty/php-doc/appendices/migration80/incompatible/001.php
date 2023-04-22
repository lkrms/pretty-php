<?php
echo "Sum: " . $a + $b;
// was previously interpreted as:
echo ("Sum: " . $a) + $b;
// is now interpreted as:
echo "Sum:" . ($a + $b);
?>