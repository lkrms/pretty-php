<?php
try {
    throw new Exception("Some error message");
} catch(Exception $e) {
    echo "The exception was created on line: " . $e->getLine();
}
?>