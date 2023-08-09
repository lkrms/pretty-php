<?php
function foo(...$args) {
    var_dump($args);
}
function gen() {
    yield 1.23 => 123;
}
foo(...gen());
?>