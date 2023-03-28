<?php
function foo(): int|INT {} // Disallowed
function foo(): bool|false {} // Disallowed
function foo(): int&Traversable {} // Disallowed
function foo(): self&Traversable {} // Disallowed

use A as B;
function foo(): A|B {} // Disallowed ("use" is part of name resolution)
function foo(): A&B {} // Disallowed ("use" is part of name resolution)

class_alias('X', 'Y');
function foo(): X|Y {} // Allowed (redundancy is only known at runtime)
function foo(): X&Y {} // Allowed (redundancy is only known at runtime)
?>