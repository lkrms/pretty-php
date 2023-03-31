<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

use Lkrms\Pretty\Php\Rule\AlignChainedCalls;

final class AddHangingIndentationTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider ternaryOperatorsProvider
     */
    public function testTernaryOperators(string $code, string $expected)
    {
        $this->assertFormatterOutputIs($code, $expected, [AlignChainedCalls::class]);
    }

    public static function ternaryOperatorsProvider(): array
    {
        return [
            [
                <<<'PHP'
                <?php

                $abc = $def->ghi()
                ->klm()
                ?: $abc;
                PHP,
                <<<'PHP'
                <?php

                $abc = $def->ghi()
                           ->klm()
                    ?: $abc;

                PHP,
            ],
            [
                <<<'PHP'
                <?php

                do
                $result = true
                ? 'true'
                ? 't'
                : false
                : 'f';
                while (false);
                PHP,
                <<<'PHP'
                <?php

                do
                    $result = true
                        ? 'true'
                            ? 't'
                            : false
                        : 'f';
                while (false);

                PHP,
            ],
        ];
    }

    /**
     * @dataProvider processTokenProvider
     */
    public function testProcessToken(string $code, string $expected)
    {
        $this->assertFormatterOutputIs($code, $expected);
    }

    public static function processTokenProvider(): array
    {
        return [
            [
                <<<'PHP'
                <?php
                $a = $b->c(fn() =>
                $d &&
                $e)
                ?: $start;
                PHP,
                <<<'PHP'
                <?php
                $a = $b->c(fn() =>
                    $d &&
                        $e)
                    ?: $start;

                PHP,
            ],
            [
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
            ],
        ];
    }
}
