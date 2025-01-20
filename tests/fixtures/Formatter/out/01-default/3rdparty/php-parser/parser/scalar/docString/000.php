<?php

// empty strings
<<<'EOS'
EOS;
<<<EOS
EOS;

// constant encapsed strings
<<<'EOS'
Test '" $a \n
EOS;
<<<EOS
Test '" \$a \n
EOS;

// encapsed strings
<<<EOS
Test $a
EOS;
<<<EOS
Test $a and $b->c test
EOS;

<<<EOS
Binary
EOS;

<<<EOS
$x\r
EOS;
