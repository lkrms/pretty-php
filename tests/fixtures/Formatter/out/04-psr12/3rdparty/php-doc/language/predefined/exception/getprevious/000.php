<?php

class MyCustomException extends Exception {}

function doStuff()
{
    try {
        throw new InvalidArgumentException('You are doing it wrong!', 112);
    } catch (Exception $e) {
        throw new MyCustomException('Something happened', 911, $e);
    }
}

try {
    doStuff();
} catch (Exception $e) {
    do {
        printf("%s:%d %s (%d) [%s]\n", $e->getFile(), $e->getLine(), $e->getMessage(), $e->getCode(), get_class($e));
    } while ($e = $e->getPrevious());
}
?>