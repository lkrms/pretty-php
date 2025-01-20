<?php

$value = match (1) {
    0, 1, => 'Foo',
    default, => 'Bar',
};