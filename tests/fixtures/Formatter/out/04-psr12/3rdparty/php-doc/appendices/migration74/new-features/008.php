<?php
// Like 2>&1 on the shell
proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
// Like 2>/dev/null or 2>nul on the shell
proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['null']], $pipes);
?>