<?php
$s = new \SensitiveParameterValue('secret');

echo 'The protected value is: ', $s->getValue(), "\n";
?>