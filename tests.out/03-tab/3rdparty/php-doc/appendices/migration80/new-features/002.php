<?php
$fn = fn() => throw new Exception('Exception in arrow function');
$user = $session->user ?? throw new Exception('Must have user');
