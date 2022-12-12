<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

final class AlignChainedCallsTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    public function testAfterTokenLoop()
    {
        $in  = <<<'PHP'
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
        PHP;
        $out = <<<'PHP'
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

        PHP;
        $this->assertFormatterOutputIs($in, $out);
    }
}
