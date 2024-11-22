<?php
if (str_contains($_SERVER['HTTP_USER_AGENT'], 'Firefox')) {
?>
<h3>str_contains() returned true</h3>
<p>You are using Firefox</p>
<?php
} else {
?>
<h3>str_contains() returned false</h3>
<p>You are not using Firefox</p>
<?php
}
?>