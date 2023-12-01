<?php
function get_contents() {
  file_get_contents("http://example.com");
  var_dump($http_response_header); // variable is populated in the local scope
}
get_contents();
var_dump($http_response_header); // a call to get_contents() does not populate the variable outside the function scope
?>