<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

final class AlignArgumentsTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    public function testAlignList()
    {
        [$in, $out] = [
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
        ];
        $this->assertFormatterOutputIs($in, $out);

        [$in, $out] = [
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
        ];
        $this->assertFormatterOutputIs($in, $out);

        [$in, $out] = [
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

            PHP
        ];
        $this->assertFormatterOutputIs($in, $out);

        // Same input as in AlignChainedCallsTest
        [$in, $out] = [
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
        ];
        $this->assertFormatterOutputIs($in, $out);
    }
}
