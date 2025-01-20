<?php

enum A implements B
{
	case X;
	case Y;

	public function foo() {}
}

enum B: int
{
	case X = 1;
	case Y = 2;
}

enum C: string implements D
{
	case Z = 'A';
}

enum D: \Foo\Bar {}
