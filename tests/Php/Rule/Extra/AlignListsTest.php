<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule\Extra;

use Lkrms\Pretty\Tests\Php\TestCase;

final class AlignListsTest extends TestCase
{
    /**
     * @dataProvider processTokenProvider
     */
    public function testProcessToken(string $code, string $expected)
    {
        $this->assertFormatterOutputIs($code, $expected);
    }

    public function processTokenProvider(): array
    {
        return [
            'array destructuring' => [
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
                <<<'PHP'
                <?php

                [$a, $b, $c,] = [
                    $d,
                    $e,
                    $f,
                ];
                [[$a, $b, $c,],] = [
                    [
                        $d,
                        $e,
                        $f,
                    ],
                ];
                list($a, $b, $c,) = [
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
                ] as [$a, $b, $c,]) {
                }
                foreach ([
                    [
                        [
                            $d,
                            $e,
                            $f,
                        ],
                    ],
                ] as [[$a, $b, $c,]]) {
                }
                foreach ([
                    [
                        $d,
                        $e,
                        $f,
                    ],
                ] as list($a, $b, $c,)) {
                }

                PHP,
            ],
        ];
    }
}
