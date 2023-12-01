<?php

$returnValue = match ($expr) {
    0       => 'First case',
    1, 2, 3 => multipleCases(),
    default => 'Default case',
};
