<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

final class AddHangingIndentationTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider ternaryOperatorsProvider
     */
    public function testTernaryOperators(string $code, string $expected)
    {
        $this->assertFormatterOutputIs($code, $expected);
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
