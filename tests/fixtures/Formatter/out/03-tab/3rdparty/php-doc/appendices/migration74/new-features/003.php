<?php

/**
 * These classes satisfy the LSP requirements, because C is a subtype of A.
 * However, at the time class B is declared, class C is not yet available
 */
class A
{
	public function method(): A {}
}

class B extends A
{
	// Fatal error: Could not check compatibility between B::method():C and
	// A::method(): A, because class С is not available
	public function method(): С {}
}

class C extends B {}

?>