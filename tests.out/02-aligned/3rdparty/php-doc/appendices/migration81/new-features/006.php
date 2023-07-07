<?php
$stmt = $mysqli->prepare('INSERT INTO users(id, name) VALUES(?,?)');
$stmt->execute([1, $username]);
?>