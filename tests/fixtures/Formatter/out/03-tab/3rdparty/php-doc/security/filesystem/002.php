<?php

// Removes a file from the hard drive that
// the PHP user has access to.
$username = $_SERVER['REMOTE_USER'];  // using an authentication mechanism
$userfile = basename($_POST['user_submitted_filename']);
$homedir = "/home/$username";

$filepath = "$homedir/$userfile";

if (file_exists($filepath) && unlink($filepath)) {
	$logstring = "Deleted $filepath\n";
} else {
	$logstring = "Failed to delete $filepath\n";
}

$fp = fopen('/home/logging/filedelete.log', 'a');
fwrite($fp, $logstring);
fclose($fp);

echo htmlentities($logstring, ENT_QUOTES);

?>