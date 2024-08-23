<?php
file_put_contents('php://memory', 'PHP');
echo file_get_contents('php://memory'); // prints nothing