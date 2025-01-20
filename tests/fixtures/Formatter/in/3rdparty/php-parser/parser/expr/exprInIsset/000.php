<?php
// This is legal.
isset(($a), (($b)));
// This is illegal, but not a syntax error.
isset(1 + 1);