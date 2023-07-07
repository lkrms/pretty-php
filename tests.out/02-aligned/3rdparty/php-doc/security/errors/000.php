<?php
if ($username) {         // Not initialized or checked before usage
    $good_login = 1;
}
if ($good_login == 1) {  // If above test fails, not initialized or checked before usage
    readfile('/highly/sensitive/data/index.html');
}
?>