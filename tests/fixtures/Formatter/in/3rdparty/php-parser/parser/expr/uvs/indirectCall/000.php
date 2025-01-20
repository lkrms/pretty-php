<?php

id('var_dump')(1);
id('id')('var_dump')(2);
id()()('var_dump')(4);
id(['udef', 'id'])[1]()('var_dump')(5);
(function($x) { return $x; })('id')('var_dump')(8);
($f = function($x = null) use (&$f) {
    return $x ?: $f;
})()()()('var_dump')(9);
[$obj, 'id']()('id')($id)('var_dump')(10);
'id'()('id')('var_dump')(12);
('i' . 'd')()('var_dump')(13);
'\id'('var_dump')(14);