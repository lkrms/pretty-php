<?php
foo(...);
$this->foo(...);
A::foo(...);

// These are invalid, but accepted on the parser level.
new Foo(...);
$this?->foo(...);

#[Foo(...)]
function foo() {}