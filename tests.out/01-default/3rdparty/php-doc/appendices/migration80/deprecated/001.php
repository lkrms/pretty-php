<?php
function test(A $a = null, $b) {}  // Still allowed
function test(?A $a, $b) {}  // Recommended
?>