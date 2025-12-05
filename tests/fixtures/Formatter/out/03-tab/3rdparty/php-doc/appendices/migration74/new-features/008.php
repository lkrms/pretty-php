<?php
proc_open(['php', '-r', 'echo "Hello World\n";'], $descriptors, $pipes);
?>