<?php

function test() {
    do_something_risky() or throw new Exception('It did not work');
}

try {
    test();
} catch (Exception $e) {
    print $e->getMessage();
}
?>