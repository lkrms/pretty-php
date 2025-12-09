<?php

#[\NoDiscard]
function concat(string $a, string $b): string {
     return $a . $b;
}

// Warning: The return value of function concat() should either be used or
// intentionally ignored by casting it as (void) in xxx.php
concat("a", "b");

// No warning, because the return value is consumed by the assignment.
$results = concat("a", "b");

// No warning, because the (void) cast is used.
(void)concat("a", "b");