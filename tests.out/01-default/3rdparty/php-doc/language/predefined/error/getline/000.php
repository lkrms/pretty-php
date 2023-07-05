<?php
try {
    throw new Error('Some error message');
} catch (Error $e) {
    echo 'The error was created on line: ' . $e->getLine();
}
?>