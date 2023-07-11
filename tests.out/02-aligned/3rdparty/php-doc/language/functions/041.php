<?php

fn(array $x)     => $x;
static fn(): int => $x;
fn($x = 42)      => $x;
fn(&$x)          => $x;
fn &($x)         => $x;
fn($x, ...$rest) => $rest;

?>