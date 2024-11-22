<?php
class Example
{
	public function __construct(
		public int $prop
	) {
		echo __METHOD__, "\n";
	}
}

$reflector = new ReflectionClass(Example::class);
$lazyObject = $reflector->newLazyGhost(function (Example $object) {
	// Initialize object in-place
	$object->__construct(1);
});

var_dump($lazyObject);
var_dump(get_class($lazyObject));

// Triggers initialization
var_dump($lazyObject->prop);
?>