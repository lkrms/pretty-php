<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

final class AlignCommentsTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider alignCommentsProvider
     */
    public function testAlignComments(string $code, string $expected)
    {
        $this->assertFormatterOutputIs($code, $expected);
    }

    public function alignCommentsProvider()
    {
        return [
            'standalone comments' => [
                <<<'PHP'
                <?php

                $a = 1;
                $b = 2;    //

                //
                $c = 3;
                PHP,
                <<<'PHP'
                <?php

                $a = 1;
                $b = 2;    //

                //
                $c = 3;

                PHP,
            ],
        ];
    }
}
