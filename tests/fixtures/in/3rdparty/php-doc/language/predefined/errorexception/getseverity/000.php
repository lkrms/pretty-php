<?php
try {
    throw new ErrorException("Exception message", 0, E_USER_ERROR);
} catch(ErrorException $e) {
    echo "This exception severity is: " . $e->getSeverity();
    var_dump($e->getSeverity() === E_USER_ERROR);
}
?>