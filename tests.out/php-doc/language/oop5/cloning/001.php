<?php
$dateTime = new DateTime();
echo (clone $dateTime)->format('Y');
?>