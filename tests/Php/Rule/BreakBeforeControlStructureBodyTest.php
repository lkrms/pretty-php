<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

use Lkrms\Pretty\Tests\Php\TestCase;

final class BreakBeforeControlStructureBodyTest extends TestCase
{
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
            'suppress empty line before closing while' => [
                <<<'PHP'
                <?php

                do
                something();

                while (false);
                PHP,
                <<<'PHP'
                <?php

                do
                    something();
                while (false);

                PHP,
            ],
        ];
    }
}
