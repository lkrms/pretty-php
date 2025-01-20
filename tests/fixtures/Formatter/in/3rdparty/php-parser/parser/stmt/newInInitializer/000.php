<?php

const C = new Foo;

function a($x = new Foo) {
    static $y = new Foo;
}

#[Attr(new Foo)]
class Bar {
    const C = new Foo;
    public $prop = new Foo;
}