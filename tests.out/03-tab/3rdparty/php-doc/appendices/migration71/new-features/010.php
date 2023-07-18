<?php
pcntl_async_signals(true);  // turn on async signals

pcntl_signal(SIGHUP, function ($sig) {
	echo "SIGHUP\n";
});

posix_kill(posix_getpid(), SIGHUP);
