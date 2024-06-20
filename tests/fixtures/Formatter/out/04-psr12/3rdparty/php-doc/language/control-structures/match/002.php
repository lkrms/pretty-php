<?php
$age = 18;

$output = match (true) {
    $age < 2 => 'Baby',
    $age < 13 => 'Child',
    $age <= 19 => 'Teenager',
    $age > 19 => 'Young adult',
    $age >= 40 => 'Old adult'
};

var_dump($output);
?>