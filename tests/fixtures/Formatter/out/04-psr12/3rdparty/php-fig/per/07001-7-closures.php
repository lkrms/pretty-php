<?php

$longArgs_noVars = function (
    $longArgument,
    $longerArgument,
    $muchLongerArgument,
) {
    // ...
};

$noArgs_longVars = function () use (
    $longVar1,
    $longerVar2,
    $muchLongerVar3,
) {
    // ...
};

$longArgs_longVars = function (
    $longArgument,
    $longerArgument,
    $muchLongerArgument,
) use (
    $longVar1,
    $longerVar2,
    $muchLongerVar3,
) {
    // ...
};

$longArgs_shortVars = function (
    $longArgument,
    $longerArgument,
    $muchLongerArgument,
) use ($var1) {
    // ...
};

$shortArgs_longVars = function ($arg) use (
    $longVar1,
    $longerVar2,
    $muchLongerVar3,
) {
    // ...
};
