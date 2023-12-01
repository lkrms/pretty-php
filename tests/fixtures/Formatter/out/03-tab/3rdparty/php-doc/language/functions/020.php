<?php
htmlspecialchars($string, double_encode: false);
// Same as
htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8', false);
?>