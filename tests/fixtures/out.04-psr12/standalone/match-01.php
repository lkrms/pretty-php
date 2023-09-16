<?php

$result = match (true) {
    str_contains($text, 'Welcome') ||
        str_contains($text, 'Hello'),
    str_contains($text, 'Hi') || str_contains($text, "G'day") => 'en',

    str_contains($text, 'Bienvenue') ||
        str_contains($text, 'Bonjour') => 'fr'
};

$result = match (true) {
    $senior,
    $age >= 65 => 'senior',
    $adult,
    $age >= 25 => 'adult',
    $youngAdult,
    $age >= 18 => 'young adult',
    default => 'kid'
};
