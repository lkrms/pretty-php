<?php

<<<'STR'
STR;
<<<STR
STR;

<<<'STR'
A
B
STR;
<<<STR
A
B
STR;

<<<'STR'
a\nb$c
STR;
<<<STR
a\\nb\$c
STR;

<<<STR
a$b
{$c->d}
STR;

call(
	<<<STR
	A
	STR,
	<<<STR
	B
	STR
);

function test()
{
	<<<'STR'
	STR;
	<<<'STR'
	Foo
	    Bar
	        Baz
	STR;
	<<<STR
	STR;
	<<<STR
	Foo
	Bar
	STR;
	<<<STR
	Bar
	    Baz
	STR;
	<<<STR
	$bar
	    $baz
	STR;
}
