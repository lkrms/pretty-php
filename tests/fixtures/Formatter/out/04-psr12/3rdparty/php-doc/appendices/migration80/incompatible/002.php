<?php

// Replace
function test(int $arg = CONST_RESOLVING_TO_NULL) {}
// With
function test(?int $arg = CONST_RESOLVING_TO_NULL) {}
// Or
function test(int $arg = null) {}
?>