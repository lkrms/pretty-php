<?php

$ary = [
    <<<FOO
        Test
        FOO,
    <<<'BAR'
        Test
        BAR,
]; <<<'END'
    END; <<<END

    END; <<<END
     
    END; <<<'END'
        a
       b

      c

     d
    e
    END; <<<END
        a
       b
      $test
     d
    e
    END; <<<'END'

        a

       b

      c

     d

    e

    END; <<<END
    \ta\r

    \ta

       b\r

      $test

     d\r

    e

    END; <<<BAR
    $one-
    BAR; <<<BAR
    $two -
    BAR; <<<BAR
    $three\t-
    BAR; <<<BAR
    $four-$four
    BAR; <<<BAR
    $five-$five-
    BAR; <<<BAR
    $six-$six-$six
    BAR; <<<BAR
    $seven
    -
    BAR; <<<BAR
    $eight
     -
    BAR; <<<BAR
    $nine
    BAR; <<<BAR
    -
    BAR; <<<BAR
     -
    BAR;
