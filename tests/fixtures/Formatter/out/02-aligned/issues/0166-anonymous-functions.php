<?php
function ($foo,
          $bar,
          $baz) use ($a,
                     $b,
                     $c) {};

// Added for completeness:

$foo = function ($bar  //
                 ) use ($baz,
                        $qux) {};

$foo = function ($bar
                 /* */) use ($baz,
                             $qux) {};

$foo = function ($bar,
                 $baz)  //
    use ($baz,
         $qux) {};

$foo = [
    10 =>
        function ($bar,
                  $baz) /* */ use (&$foo,
                                   $qux) {},
];

function ($foo,
          $bar,
          $baz) use ($a,
                     $b,
                     $c) {
    quux();
};

$foo = function ($bar  //
                 ) use ($baz,
                        $qux) {
    quux();
};

$foo = function ($bar
                 /* */) use ($baz,
                             $qux) {
    quux();
};

$foo = function ($bar,
                 $baz)  //
    use ($baz,
         $qux) {
        quux();
    };

$foo = [
    10 =>
        function ($bar,
                  $baz) /* */ use (&$foo,
                                   $qux) {
            quux();
        },
];
