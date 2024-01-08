<?php

class Base
{
	protected function foo(): void {}
}

final class Extended extends Base
{
	#[\Override]
	protected function boo(): void {}
}

?>