<?php

enum A {
    case class;
}
enum B implements Bar, Baz {
}
enum C: int implements Bar {
    case Foo = 1;
}