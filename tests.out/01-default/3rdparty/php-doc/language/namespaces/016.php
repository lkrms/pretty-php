<?php
namespace MyProject;

use blah\blah as mine;  // see "Using namespaces: Aliasing/Importing"

blah\mine();  // calls function MyProject\blah\mine()
namespace\blah\mine();  // calls function MyProject\blah\mine()

namespace\func();  // calls function MyProject\func()
namespace\sub\func();  // calls function MyProject\sub\func()
namespace\cname::method();  // calls static method "method" of class MyProject\cname
$a = new namespace\sub\cname();  // instantiates object of class MyProject\sub\cname
$b = namespace\CONSTANT;  // assigns value of constant MyProject\CONSTANT to $b
?>