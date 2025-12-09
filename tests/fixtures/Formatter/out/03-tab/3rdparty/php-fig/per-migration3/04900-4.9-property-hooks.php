<?php

class Example
{
	public string $one = 'Me' {
		set (string $value) {
			// ...
		}
	}

	public string $two {
		get {
			// ...
		}
		set {
			// ...
		}
	}

	public string $three {
		get => __CLASS__;
		set => ucfirst($value);
	}

	public string $four {
		get => __CLASS__;
	}
}
