<?php

$username = $_SERVER['REMOTE_USER'];  // using an authentication mechanisim
$userfile = $_POST['user_submitted_filename'];
$homedir = "/home/$username";

$filepath = "$homedir/$userfile";

if (!ctype_alnum($username) || !preg_match('/^(?:[a-z0-9_-]|\.(?!\.))+$/iD', $userfile)) {
	die('Bad username/filename');
}

// etc.

?>