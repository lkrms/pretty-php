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

    public static function alignCommentsProvider()
    {
        return [
            'standalone comments' => [
                <<<'PHP'
                <?php

                $a = 1;
                $b = 2;  //

                //
                $c = 3;
                PHP,
                <<<'PHP'
                <?php

                $a = 1;
                $b = 2;  //

                //
                $c = 3;

                PHP,
            ],
            [
                <<<'PHP'
                <?php
                echo 'This is a test'; // This is a one-line c++ style comment
                /* This is a multi line comment
                   yet another line of comment */
                echo 'This is yet another test';
                echo 'One Final Test'; # This is a one-line shell-style comment
                ?>
                PHP,
                <<<'PHP'
                <?php
                echo 'This is a test';  // This is a one-line c++ style comment
                /* This is a multi line comment
                   yet another line of comment */
                echo 'This is yet another test';
                echo 'One Final Test';  # This is a one-line shell-style comment
                ?>
                PHP,
            ]
        ];
    }
}
