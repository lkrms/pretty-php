<?php

class A
{
	use B, C, D {
		f as g;
		f as private;
		f as private g;
		B::f as g;
		B::f insteadof C, D;
	}
}
