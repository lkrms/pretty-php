<?php
/* This is equivalent to simply:
  readfile("http://www.example.com");
  since no filters are actually specified */

readfile('php://filter/resource=http://www.example.com');
?>