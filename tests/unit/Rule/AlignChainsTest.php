<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignChains;
use Lkrms\PrettyPHP\Rule\AlignTernaryOperators;
use Lkrms\PrettyPHP\Tests\TestCase;

final class AlignChainsTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [AlignChains::class, AlignTernaryOperators::class]);
    }

    /**
     * @return iterable<array{string,string}>
     */
    public static function outputProvider(): iterable
    {
        yield from [
            [
                <<<'PHP'
<?php
func()->call1($a1 ||
              $b1,
          $c1,
          $d1 || $e1)
      ->call2($a2 ||
              $b2,
          $c2,
          $d2 || $e2)
      ->call3($a3 ||
              $b3,
          $c3,
          $d3 || $e3);

PHP,
                <<<'PHP'
<?php
func()->call1($a1 ||
$b1,
$c1,
$d1 || $e1)
->call2($a2 ||
$b2,
$c2,
$d2 || $e2)
->call3($a3 ||
$b3,
$c3,
$d3 || $e3);
PHP,
            ],
            [
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
            [
                <<<'PHP'
<?php
$foo = $bar
           ?: $foo->bar()
                  ->baz() ?: $foo->baz()
                                 ->qux();

PHP,
                <<<'PHP'
<?php
$foo = $bar
?: $foo->bar()
->baz() ?: $foo->baz()
->qux();
PHP,
            ],
        ];

        if (\PHP_VERSION_ID < 80000) {
            return;
        }

        yield from [
            [
                <<<'PHP'
<?php
func()?->call1($a1 ||
               $b1,
           $c1,
           $d1 || $e1)
      ?->call2($a2 ||
               $b2,
           $c2,
           $d2 || $e2)
       ->call3($a3 ||
               $b3,
           $c3,
           $d3 || $e3);

PHP,
                <<<'PHP'
<?php
func()?->call1($a1 ||
$b1,
$c1,
$d1 || $e1)
?->call2($a2 ||
$b2,
$c2,
$d2 || $e2)
->call3($a3 ||
$b3,
$c3,
$d3 || $e3);
PHP,
            ],
            [
                <<<'PHP'
<?php
func()->call1($a1 ||
              $b1,
          $c1,
          $d1 || $e1)
     ?->call2($a2 ||
              $b2,
          $c2,
          $d2 || $e2)
      ->call3($a3 ||
              $b3,
          $c3,
          $d3 || $e3);

PHP,
                <<<'PHP'
<?php
func()->call1($a1 ||
$b1,
$c1,
$d1 || $e1)
?->call2($a2 ||
$b2,
$c2,
$d2 || $e2)
->call3($a3 ||
$b3,
$c3,
$d3 || $e3);
PHP,
            ],
            [
                <<<'PHP'
<?php
fnA()?->call1($a1 ||
              $b1,
          fnB()->call2($a2 ||
                        $b2,
                    $c1,
                    $d1 || $e1)
              ?->call3($a3 ||
                        $b3,
                    $c2,
                    $d2 || $e2)
              ?->call4($a4 ||
                        $b4,
                    $c3,
                    $d3 || $e3),
          $d4 || $e4)
      ->call5($a5 ||
              $b5,
          fnC()?->call6($a6 ||
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
     ?->call9($a9 ||
              $b9,
          fnD()->call10($a10 ||
                       $b10,
                   $c7,
                   $d9 || $e9)
               ->call11($a11 ||
                       $b11,
                   $c8,
                   $d10 || $e10)
              ?->call12($a12 ||
                       $b12,
                   $c9,
                   $d11 || $e11),
          $d12 || $e12);

PHP,
                <<<'PHP'
<?php
fnA()?->call1($a1 ||
$b1,
fnB()->call2($a2 ||
$b2,
$c1,
$d1 || $e1)
?->call3($a3 ||
$b3,
$c2,
$d2 || $e2)
?->call4($a4 ||
$b4,
$c3,
$d3 || $e3),
$d4 || $e4)
->call5($a5 ||
$b5,
fnC()?->call6($a6 ||
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
?->call9($a9 ||
$b9,
fnD()->call10($a10 ||
$b10,
$c7,
$d9 || $e9)
->call11($a11 ||
$b11,
$c8,
$d10 || $e10)
?->call12($a12 ||
$b12,
$c9,
$d11 || $e11),
$d12 || $e12);
PHP,
            ],
            [
                <<<'PHP'
<?php
$foo = $bar
           ?: $foo?->bar()
                   ->baz() ?: $foo->baz()
                                 ?->qux();

PHP,
                <<<'PHP'
<?php
$foo = $bar
?: $foo?->bar()
->baz() ?: $foo->baz()
?->qux();
PHP,
            ],
        ];
    }
}
