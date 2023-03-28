<?php
try {
    throw new Exception;
} catch (Exception $e) {
    echo $e->getFile();
}
?>