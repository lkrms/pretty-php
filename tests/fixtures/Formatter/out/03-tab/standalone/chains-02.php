<?php

$foo = "B{$A}R"::${baz()}()
	->qux()
	->quux();

$foo = "B{$A}R"::${baz()}()
	->qux();

$foo = "B${A}R"::${baz()}()
	->qux()
	->quux();

$foo = "B${A}R"::${baz()}()
	->qux();
