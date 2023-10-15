<?php

// $uid: ' or uid like '%admin%
$query = "UPDATE usertable SET pwd='...' WHERE uid='' or uid like '%admin%';";

// $pwd: hehehe', trusted=100, admin='yes
$query = "UPDATE usertable SET pwd='hehehe', trusted=100, admin='yes' WHERE
...;";

?>