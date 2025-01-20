<?php
readonly class Rectangle
{
	// A virtual property.
	public int $area {
		get => $this->h * $this->w;
	}

	public function __construct(
		public int $h,
		public int $w
	) {}
}

$s = new Rectangle(4, 5);
print $s->area;  // prints 20
$s->area = 30;  // Error, as there is no set operation defined.
?>