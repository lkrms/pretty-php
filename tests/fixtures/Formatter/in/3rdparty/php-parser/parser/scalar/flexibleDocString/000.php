<?php

$ary = [
    <<<FOO
Test
FOO,
    <<<'BAR'
    Test
    BAR,
];

<<<'END'
 END;

<<<END

  END;

<<<END
 
  END;

<<<'END'
     a
    b

   c

  d
 e
 END;

<<<END
	    a
	   b
	  $test
	 d
	e
	END;

<<<'END'

    a

   b

  c

 d

e

END;

<<<END
	a\r\n
\ta\n
   b\r\n
  $test\n
 d\r\n
e\n
END;

<<<BAR
 $one-
 BAR;

<<<BAR
 $two -
 BAR;

<<<BAR
 $three	-
 BAR;

<<<BAR
 $four-$four
 BAR;

<<<BAR
 $five-$five-
 BAR;

<<<BAR
 $six-$six-$six
 BAR;

<<<BAR
 $seven
 -
 BAR;

<<<BAR
 $eight
  -
 BAR;

<<<BAR
$nine
BAR;

<<<BAR
 -
 BAR;

<<<BAR
  -
 BAR;