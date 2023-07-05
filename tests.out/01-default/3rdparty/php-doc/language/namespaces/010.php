<?php
namespace Foo;

function strlen() {}

const INI_ALL = 3;

class Exception {}

$a = \strlen('hi');  // calls global function strlen
$b = \INI_ALL;  // accesses global constant INI_ALL
$c = new \Exception('error');  // instantiates global class Exception
?>