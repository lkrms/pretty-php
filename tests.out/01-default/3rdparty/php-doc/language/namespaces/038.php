<?php
namespace my\stuff;

include 'file1.php';
include 'another.php';

use another\thing as MyClass;

$a = new MyClass;  // instantiates class "thing" from namespace another
?>