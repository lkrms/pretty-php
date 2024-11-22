<?php
echo (new DateTime())->format('Y');
// surrounding parentheses are optional as of PHP 8.4.0
echo new DateTime()->format('Y');
?>