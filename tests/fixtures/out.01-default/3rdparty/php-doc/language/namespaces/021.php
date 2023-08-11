<?php
use My\Full\Classname as Another, My\Full\NSname;

$obj = new Another;  // instantiates object of class My\Full\Classname
$obj = new \Another;  // instantiates object of class Another
$obj = new Another\thing;  // instantiates object of class My\Full\Classname\thing
$obj = new \Another\thing;  // instantiates object of class Another\thing
?>