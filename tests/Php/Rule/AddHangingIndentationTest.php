<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

final class AddHangingIndentationTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    public function testMaybeCollapseOverhanging()
    {
        [$in, $out] = [
            <<<'PHP'
            <?php

            if ($a &&
            ($b ||
            $c) &&
            $d) {
            $e;
            }
            PHP,
            <<<'PHP'
            <?php

            if ($a &&
                    ($b ||
                        $c) &&
                    $d) {
                $e;
            }

            PHP,
        ];
        $this->assertFormatterOutputIs($in, $out);
    }
}
