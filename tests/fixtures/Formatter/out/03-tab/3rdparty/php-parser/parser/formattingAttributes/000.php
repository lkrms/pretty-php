<?php

0b1100;
014;
12;
0xC;
123_456;
3.141_592_653;

'foo';
'bar';
'foo
bar';
"foo\nbar";
"foo\nbar{$x}";
`foo\nbar`;
`foo\nbar{$x}`;

<<<'ABC'
ABC;
<<<'ABC'
foo bar
ABC;
<<<'ABC'
foo bar
ABC;
<<<ABC
foo\nbar
ABC;
<<<ABC
foo\nbar
ABC;
<<<ABC
foo\nbar{$x}baz
ABC;
<<<ABC
foo\nbar{$x}baz
ABC;

array();
[];
list($x) = $y;
[$x] = $y;
(int) $int;
(int) $integer;
(bool) $bool;
(bool) $boolean;
(string) $string;
(string) $binary;
