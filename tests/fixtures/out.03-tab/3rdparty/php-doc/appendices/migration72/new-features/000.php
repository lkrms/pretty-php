<?php

function test(object $obj): object
{
	return new SplQueue();
}

test(new stdClass());
