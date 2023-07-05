<?php

var_dump(
    count(null),  // NULL is not countable
    count(1),  // integers are not countable
    count('abc'),  // strings are not countable
    count(new stdClass),  // objects not implementing the Countable interface are not countable
    count([1, 2])  // arrays are countable
);
