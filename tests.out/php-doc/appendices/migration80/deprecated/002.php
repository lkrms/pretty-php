<?php
// Replace
usort($array, fn($a, $b) => $a > $b);
// With
usort($array, fn($a, $b) => $a <=> $b);
?>