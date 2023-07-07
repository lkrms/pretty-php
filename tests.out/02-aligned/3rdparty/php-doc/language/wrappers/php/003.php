<?php
/* This will filter the string "Hello World"
  through the rot13 filter, then write to
  example.txt in the current directory */
file_put_contents('php://filter/write=string.rot13/resource=example.txt', 'Hello World');
?>