<?php
try {
    throw new Exception("Some error message");
} catch (Exception $e) {
    echo $e->getMessage();
}
?>