<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignChains;
use Lkrms\PrettyPHP\Rule\AlignLists;
use Lkrms\PrettyPHP\Tests\TestCase;

final class AlignListsTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [AlignLists::class, AlignChains::class]);
    }

    /**
     * @return array<array{string,string}>
     */
    public static function outputProvider(): array
    {
        return [
            'array destructuring' => [
                <<<'PHP'
<?php
[
    $a,
    $b,
    $c,
] = [
    $d,
    $e,
    $f,
];
[
    [
        $a,
        $b,
        $c,
    ],
] = [
    [
        $d,
        $e,
        $f,
    ],
];
list(
    $a,
    $b,
    $c,
) = [
    $d,
    $e,
    $f,
];
foreach ([
    [
        $d,
        $e,
        $f,
    ],
] as [
    $a,
    $b,
    $c,
]) {
}
foreach ([
    [
        [
            $d,
            $e,
            $f,
        ],
    ],
] as [[
          $a,
          $b,
          $c,
      ]]) {
}
foreach ([
    [
        $d,
        $e,
        $f,
    ],
] as list(
    $a,
    $b,
    $c,
)) {
}

PHP,
                <<<'PHP'
<?php
[$a, $b, $c,] = [$d, $e, $f,];
[[$a, $b, $c,],] = [[$d, $e, $f,],];
list($a, $b, $c,) = [$d, $e, $f,];
foreach ([[$d, $e, $f,],] as [$a, $b, $c,]) {
}
foreach ([[[$d, $e, $f,],],] as [[$a, $b, $c,]]) {
}
foreach ([[$d, $e, $f,],] as list($a, $b, $c,)) {
}
PHP,
            ],
            'nested lists' => [
                <<<'PHP'
<?php
if ($a &&
    call([$b,
          $c],
         $d ||
             $e,
         $f)) {
    //
}

if ($a &&
    call([$b,
          $c],
         $d || $e,
         $f)) {
    //
}

PHP,
                <<<'PHP'
<?php
if ($a &&
call([$b,
$c],
$d ||
$e,
$f)) {
//
}

if ($a &&
call([$b,
$c],
$d || $e,
$f)) {
//
}
PHP,
            ],
            'nested lists + alternative syntax' => [
                <<<'PHP'
<?php
if ($a &&
    call([$b,
          $c],
         $d ||
             $e,
         $f)):
    //
endif;

if ($a &&
    call([$b,
          $c],
         $d || $e,
         $f)):
    //
endif;

PHP,
                <<<'PHP'
<?php
if ($a &&
call([$b,
$c],
$d ||
$e,
$f)):
//
endif;

if ($a &&
call([$b,
$c],
$d || $e,
$f)):
//
endif;
PHP,
            ],
            'nested lists + unenclosed control structure body' => [
                <<<'PHP'
<?php
if ($a &&
    call([$b,
          $c],
         $d ||
             $e,
         $f))
    foo();

if ($a &&
    call([$b,
          $c],
         $d || $e,
         $f))
    foo();

PHP,
                <<<'PHP'
<?php
if ($a &&
call([$b,
$c],
$d ||
$e,
$f))
foo();

if ($a &&
call([$b,
$c],
$d || $e,
$f))
foo();
PHP,
            ],
            'nested arrays' => [
                <<<'PHP'
<?php
$v = [[[$a ||
            $b,
        $c],
       $d],
      $e];

PHP,
                <<<'PHP'
<?php
$v = [[[$a ||
$b,
$c],
$d],
$e];
PHP,
            ],
            'with method chains' => [
                <<<'PHP'
<?php
fnA()->call1(fnB()->call2(fnC()->call3($a1 ||
                                           $b1,
                                       $c1 ||
                                           $d1)
                               ->call4($a2 ||
                                           $b2,
                                       $c2)
                               ->call5(),
                          fnD()->call6($a3 ||
                                           $b3,
                                       $c3)
                               ->call7($a4 ||
                                           $b4,
                                       $c4)
                               ->call8(),
                          $e1)
                  ->call9($a5 ||
                              $b5,
                          $c5)
                  ->call10($a6 ||
                               $b6,
                           $c6),
             $e2)
     ->call11($a7 ||
                  $b7,
              $c7)
     ->call12($a8 ||
                  $b8,
              $c8);

PHP,
                <<<'PHP'
<?php
fnA()->call1(fnB()->call2(fnC()->call3($a1 ||
$b1,
$c1 ||
$d1)->call4($a2 ||
$b2,
$c2)
->call5(),
fnD()->call6($a3 ||
$b3,
$c3)->call7($a4 ||
$b4,
$c4)
->call8(),
$e1)
->call9($a5 ||
$b5,
$c5)
->call10($a6 ||
$b6,
$c6),
$e2)
->call11($a7 ||
$b7,
$c7)
->call12($a8 ||
$b8,
$c8);
PHP,
            ],
            'with method chain variants' => [
                <<<'PHP'
<?php
fnA()->call1($a1 ||
                 $b1,
             fnB()->call2($a2 ||
                              $b2,
                          $c1,
                          $d1 || $e1)
                  ->call3($a3 ||
                              $b3,
                          $c2,
                          $d2 || $e2)
                  ->call4($a4 ||
                              $b4,
                          $c3,
                          $d3 || $e3),
             $d4 || $e4)
     ->call5($a5 ||
                 $b5,
             fnC()->call6($a6 ||
                              $b6,
                          $c4,
                          $d5 || $e5)
                  ->call7($a7 ||
                              $b7,
                          $c5,
                          $d6 || $e6)
                  ->call8($a8 ||
                              $b8,
                          $c6,
                          $d7 || $e7),
             $d8 || $e8)
     ->call9($a9 ||
                 $b9,
             fnD()->call10($a10 ||
                               $b10,
                           $c7,
                           $d9 || $e9)
                  ->call11($a11 ||
                               $b11,
                           $c8,
                           $d10 || $e10)
                  ->call12($a12 ||
                               $b12,
                           $c9,
                           $d11 || $e11),
             $d12 || $e12);

PHP,
                <<<'PHP'
<?php
fnA()->call1($a1 ||
$b1,
fnB()->call2($a2 ||
$b2,
$c1,
$d1 || $e1)
->call3($a3 ||
$b3,
$c2,
$d2 || $e2)
->call4($a4 ||
$b4,
$c3,
$d3 || $e3),
$d4 || $e4)
->call5($a5 ||
$b5,
fnC()->call6($a6 ||
$b6,
$c4,
$d5 || $e5)
->call7($a7 ||
$b7,
$c5,
$d6 || $e6)
->call8($a8 ||
$b8,
$c6,
$d7 || $e7),
$d8 || $e8)
->call9($a9 ||
$b9,
fnD()->call10($a10 ||
$b10,
$c7,
$d9 || $e9)
->call11($a11 ||
$b11,
$c8,
$d10 || $e10)
->call12($a12 ||
$b12,
$c9,
$d11 || $e11),
$d12 || $e12);
PHP,
            ],
            'with different bracket placements' => [
                <<<'PHP'
<?php
F($a + (
      $b - $c
  ) / ($e
      * $f), $g + (
                 $h / $i
             ));

F($a + (
      $b - $c
  ) / ($e
      * $f), $g + (
                 $h / $i
             ), $j + (
                    $k * $i
                ));

F(1 / (($e
          * $f) / (
      $b - $c
  )) + $a, $g + (
               $h / $i
           ));

F($a + (
      $b - $c
  ) / (
      $e
      * $f
  ), $g + (
         $h / $i
     ));

F($a + (
      $b - $c
  ) / (
      $e
      * $f
  ),
  $g + (
      $h / $i
  ));

PHP,
                <<<'PHP'
<?php
F($a + (
    $b - $c
) / ($e
    * $f), $g + (
    $h / $i
));

F($a + (
    $b - $c
) / ($e
    * $f), $g + (
    $h / $i
), $j + (
    $k * $i
));

F(1 / (($e
        * $f) / (
    $b - $c
)) + $a, $g + (
    $h / $i
));

F($a + (
    $b - $c
) / (
    $e
        * $f
), $g + (
    $h / $i
));

F($a + (
    $b - $c
) / (
    $e
        * $f
),
$g + (
    $h / $i
));
PHP,
            ],
            'with wide expressions and heredocs' => [
                <<<'PHP'
<?php
alpha()->bravo([0], [1, 2,
                     3], [4,
                          5, 6], [7,
                                  8],
               [9, 10],
               [11,
                12], [13,
                      14])
       ->charlie(['a'], ['b', 'c', ['d',
                                    'e'], 'f'], ['g', <<<EOF
                                                      h
                                                      EOF, 'i'], [<<<EOF
                                                                  j
                                                                  EOF], ['k',
                                                                         'l'],
                 ['m', <<<EOF
                       n
                       EOF],
                 ['o',
                  'p'], ['q',
                         'r']);

PHP,
                <<<'PHP'
<?php
alpha()->bravo([0], [1, 2,
3], [4,
5, 6], [7,
8],
[9, 10],
[11,
12], [13,
14])
->charlie(['a'], ['b', 'c', ['d',
'e'], 'f'], ['g', <<<EOF
h
EOF, 'i'], [<<<EOF
j
EOF], ['k',
'l'],
['m', <<<EOF
n
EOF],
['o',
'p'], ['q',
'r']);
PHP,
            ],
            'interface lists' => [
                <<<'PHP'
<?php
interface Foo extends Bar,
                      Baz,
                      Qux {}

class Foo extends Bar implements Baz,
                                 Qux {}

PHP,
                <<<'PHP'
<?php
interface Foo extends Bar,
    Baz,
    Qux {}
class Foo extends Bar implements Baz,
    Qux {}
PHP,
            ],
        ];
    }
}
