<?php

fn($a) => $a;
fn($x = 42) => $x;
fn(&$x) => $x;
fn&($x) => $x;
static fn($x, ...$rest) => $rest;
fn(): int => $x;
fn($a, $b) => $a and $b;
fn($a, $b) => $a && $b;
