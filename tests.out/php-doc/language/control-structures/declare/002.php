<?php

declare(ticks=1);

// A function called on each tick event
function tick_handler()
{
    echo "tick_handler() called\n";
}

register_tick_function('tick_handler'); // causes a tick event

$a = 1; // causes a tick event

if ($a > 0) {
    $a += 2; // causes a tick event
    print($a); // causes a tick event
}

?>