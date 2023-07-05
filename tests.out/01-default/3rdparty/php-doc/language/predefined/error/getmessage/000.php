<?php
try {
    throw new Error('Some error message');
} catch (Error $e) {
    echo $e->getMessage();
}
?>