<?php
// This is valid:
declare(ticks=1);

// This is invalid:
const TICK_VALUE = 1;

declare(ticks=TICK_VALUE);
?>