<?php
fn(bool $a) => $a;
fn($x = 42) => $x;
static fn(&$x) => $x;
fn&($x) => $x;
fn($x, ...$rest) => $rest;
fn(): int => $x;

fn($a, $b) => $a and $b;
fn($a, $b) => $a && $b;