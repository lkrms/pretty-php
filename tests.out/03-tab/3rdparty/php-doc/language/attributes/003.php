<?php

function dumpMyAttributeData($reflection)
{
	$attributes = $reflection->getAttributes(MyAttribute::class);

	foreach ($attributes as $attribute) {
		var_dump($attribute->getName());
		var_dump($attribute->getArguments());
		var_dump($attribute->newInstance());
	}
}

dumpMyAttributeData(new ReflectionClass(Thing::class));
