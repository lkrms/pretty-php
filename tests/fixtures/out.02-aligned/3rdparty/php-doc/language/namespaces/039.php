<?php
namespace my\stuff;

use another\thing as MyClass;

class MyClass {}  // fatal error: MyClass conflicts with import statement
$a = new MyClass;
?>