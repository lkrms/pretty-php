<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule\Extra;

use Lkrms\Pretty\Php\Rule\AlignLists;
use Lkrms\Pretty\Php\Rule\BreakBetweenMultiLineItems;
use Lkrms\Pretty\Php\Rule\Extra\BreakBeforeMultiLineList;
use Lkrms\Pretty\Tests\Php\TestCase;

final class BreakBetweenMultiLineItemsTest extends TestCase
{
    /**
     * @dataProvider processTokenProvider
     */
    public function testProcessToken(string $code, string $expected)
    {
        $this->assertFormatterOutputIs($code,
                                       $expected,
                                       [AlignLists::class],
                                       [BreakBetweenMultiLineItems::class]);
    }

    public function processTokenProvider(): array
    {
        return [
            'multi-line array' => [
                <<<'PHP'
                <?php

                $a = [$b,
                $c, $d];
                PHP,
                <<<'PHP'
                <?php

                $a = [$b,
                    $c,
                    $d];

                PHP,
            ],
            'multi-line array with opening newline' => [
                <<<'PHP'
                <?php

                $a = [
                $b, $c, $d];
                PHP,
                <<<'PHP'
                <?php

                $a = [
                    $b,
                    $c,
                    $d
                ];

                PHP,
            ],
            'multi-line array with multi-line element' => [
                <<<'PHP'
                <?php

                $a = [($b ||
                $c), $d,
                $e, $f];
                PHP,
                <<<'PHP'
                <?php

                $a = [($b ||
                        $c),
                    $d,
                    $e,
                    $f];

                PHP,
            ],
            'one-line array' => [
                <<<'PHP'
                <?php

                $a = [$b, $c];
                PHP,
                <<<'PHP'
                <?php

                $a = [$b, $c];

                PHP,
            ],
            'one-line array with multi-line element' => [
                <<<'PHP'
                <?php

                $a = [($b ||
                $c), $d];
                PHP,
                <<<'PHP'
                <?php

                $a = [($b ||
                    $c), $d];

                PHP,
            ],
        ];
    }

    /**
     * @dataProvider withBreakBeforeMultiLineListProvider
     */
    public function testWithBreakBeforeMultiLineList(string $code, string $expected)
    {
        $this->assertFormatterOutputIs($code,
                                       $expected,
                                       [AlignLists::class],
                                       [BreakBetweenMultiLineItems::class,
                                        BreakBeforeMultiLineList::class]);
    }

    public function withBreakBeforeMultiLineListProvider(): array
    {
        return [
            'multi-line array' => [
                <<<'PHP'
                <?php

                $a = [$b,
                $c, $d];
                PHP,
                <<<'PHP'
                <?php

                $a = [
                    $b,
                    $c,
                    $d
                ];

                PHP,
            ],
            'multi-line array with opening newline' => [
                <<<'PHP'
                <?php

                $a = [
                $b, $c, $d];
                PHP,
                <<<'PHP'
                <?php

                $a = [
                    $b,
                    $c,
                    $d
                ];

                PHP,
            ],
            'multi-line array with multi-line element' => [
                <<<'PHP'
                <?php

                $a = [($b ||
                $c), $d,
                $e, $f];
                PHP,
                <<<'PHP'
                <?php

                $a = [
                    ($b ||
                        $c),
                    $d,
                    $e,
                    $f
                ];

                PHP,
            ],
            'one-line array' => [
                <<<'PHP'
                <?php

                $a = [$b, $c];
                PHP,
                <<<'PHP'
                <?php

                $a = [$b, $c];

                PHP,
            ],
            'one-line array with multi-line element' => [
                <<<'PHP'
                <?php

                $a = [($b ||
                $c), $d];
                PHP,
                <<<'PHP'
                <?php

                $a = [($b ||
                    $c), $d];

                PHP,
            ],
        ];
    }
}
