<?php

function testReturnA(): ?string
{
	return 'elePHPant';
}

var_dump(testReturnA());

function testReturnB(): ?string
{
	return null;
}

var_dump(testReturnB());

function test(?string $name)
{
	var_dump($name);
}

test('elePHPant');
test(null);
test();
