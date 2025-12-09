<?php

interface Example
{
	public string $readable { get; }

	public string $writeable { set; }

	public string $both { get; set; }
}
