<?php
// == is an operator which tests
// equality and returns a boolean
if ($action == 'show_version') {
    echo 'The version is 1.23';
}

// this is not necessary...
if ($show_separators == TRUE) {
    echo "<hr>\n";
}

// ...because this can be used with exactly the same meaning:
if ($show_separators) {
    echo "<hr>\n";
}
?>