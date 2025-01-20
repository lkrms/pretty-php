<?php

abstract class A
{
	// Extending classes must have a publicly-gettable property
	abstract public string $readable { get; }

	// Extending classes must have a protected- or public-writeable property
	abstract protected string $writeable { set; }

	// Extending classes must have a protected or public symmetric property
	abstract protected string $both { get; set; }
}

class C extends A
{
	// This satisfies the requirement and also makes it settable, which is valid
	public string $readable;

	// This would NOT satisfy the requirement, as it is not publicly readable
	protected string $readable;

	// This satisfies the requirement exactly, so is sufficient.
	// It may only be written to, and only from protected scope
	protected string $writeable {
		set => $value;
	}

	// This expands the visibility from protected to public, which is fine
	public string $both;
}

?>