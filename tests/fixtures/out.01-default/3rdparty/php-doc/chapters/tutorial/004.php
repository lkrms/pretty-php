<?php
if (strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') !== false) {
?>
<h3>strpos() must have returned non-false</h3>
<p>You are using Firefox</p>
<?php
} else {
?>
<h3>strpos() must have returned false</h3>
<p>You are not using Firefox</p>
<?php
}
?>