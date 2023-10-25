<?php

$func = static function() {
    // function body
};
$func = $func->bindTo(new stdClass);
$func();

?>