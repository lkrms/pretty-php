<?php

0b1100;
0o14;
12;
0xc;
1_2_3_4_5_6;
3.141_592_653;

'foo';
"bar";
"foo
bar";
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
(integer) $integer;
(bool) $bool;
(boolean) $boolean;
(string) $string;
(binary) $binary;