<?php
function test($a, ...$b) {}
function test($a, &...$b) {}
function test($a, Type ...$b) {}
function test($a, Type &...$b) {}
