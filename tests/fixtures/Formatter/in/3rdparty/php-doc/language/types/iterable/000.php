<?php

function gen(): iterable {
    yield 1;
    yield 2;
    yield 3;
}

foreach(gen() as $value) {
    echo $value, "\n";
}
?>