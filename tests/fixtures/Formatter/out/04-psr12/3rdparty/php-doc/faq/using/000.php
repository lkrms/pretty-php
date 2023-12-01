<?php
$empty = $post = array();
foreach ($_POST as $varname => $varvalue) {
    if (empty($varvalue)) {
        $empty[$varname] = $varvalue;
    } else {
        $post[$varname] = $varvalue;
    }
}

print '<pre>';
if (empty($empty)) {
    print "None of the POSTed values are empty, posted:\n";
    var_dump($post);
} else {
    print 'We have ' . count($empty) . " empty values\n";
    print "Posted:\n";
    var_dump($post);
    print "Empty:\n";
    var_dump($empty);
    exit;
}
?>