<?php

$closureWithArgs = function ($arg1, $arg2) {
	// ...
};

$closureWithArgsAndVars = function ($arg1, $arg2) use ($var1, $var2) {
	// ...
};

$closureWithArgsVarsAndReturn = function ($arg1, $arg2) use ($var1, $var2): bool {
	// ...
};
