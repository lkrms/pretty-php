<?php
namespace foo;

use blah\blah as foo;

$a = new name();  // instantiates "foo\name" class
foo::name();      // calls static method "name" in class "blah\blah"
?>