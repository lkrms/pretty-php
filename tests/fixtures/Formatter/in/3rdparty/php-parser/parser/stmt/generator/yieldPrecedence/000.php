<?php

function gen() {
    yield "a" . "b";
    yield "a" or die;
    yield "k" => "a" . "b";
    yield "k" => "a" or die;
    var_dump([yield "k" => "a" . "b"]);
    yield yield "k1" => yield "k2" => "a" . "b";
    yield yield "k1" => (yield "k2") => "a" . "b";
    var_dump([yield "k1" => yield "k2" => "a" . "b"]);
    var_dump([yield "k1" => (yield "k2") => "a" . "b"]);
}