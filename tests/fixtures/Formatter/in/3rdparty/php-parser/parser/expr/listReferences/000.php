<?php

list(&$v) = $x;
list('k' => &$v) = $x;
[&$v] = $x;
['k' => &$v] = $x;