<?php
$proc = proc_open($command, [['pty'], ['pty'], ['pty']], $pipes);
?>