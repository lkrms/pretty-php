<?php
// Example usage for: Null Coalesce Operator
$action = $_POST['action'] ?? 'default';

// The above is identical to this if/else statement
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else {
	$action = 'default';
}
?>