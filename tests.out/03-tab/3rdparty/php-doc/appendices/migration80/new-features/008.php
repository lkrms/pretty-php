<?php
// OK even if $excludes is empty:
array_diff($array, ...$excludes);
// OK even if $arrays only contains a single array:
array_intersect(...$arrays);
?>