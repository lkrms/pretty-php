<?php

class Talker
{
	use A;
	use B {
		A::smallTalk insteadof B;
	}
	use C {
		B::bigTalk insteadof C;
		C::mediumTalk as FooBar;
	}
}
