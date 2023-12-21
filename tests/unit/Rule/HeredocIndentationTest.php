<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class HeredocIndentationTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     *
     * @param Formatter|FormatterB $formatter
     */
    public function testOutput(string $expected, string $code, $formatter): void
    {
        $this->assertFormatterOutputIs($expected, $code, $formatter);
    }

    /**
     * @return array<array{string,string,Formatter|FormatterB}>
     */
    public static function outputProvider(): array
    {
        $formatterB = Formatter::build();

        return [
            'NONE' => [
                <<<'PHP'
<?php
$array = [
    <<<EOF
Fugiat magna laborum ut occaecat sit nostrud non eiusmod laboris nisi.
EOF
];

PHP,
                <<<'PHP'
<?php
$array = [
<<<EOF
Fugiat magna laborum ut occaecat sit nostrud non eiusmod laboris nisi.
EOF
];
PHP,
                $formatterB
                    ->heredocIndent(HeredocIndent::NONE),
            ],
            'LINE' => [
                <<<'PHP'
<?php
$getString = function () {
    return <<<EOF
    Incididunt in sint sit aliqua pariatur ad.
    EOF;
};

PHP,
                <<<'PHP'
<?php
$getString = function () {
return <<<EOF
Incididunt in sint sit aliqua pariatur ad.
EOF;
};
PHP,
                $formatterB
                    ->heredocIndent(HeredocIndent::LINE),
            ],
            'MIXED' => [
                <<<'PHP'
<?php
$string1 = <<<EOF
    Enim Lorem nostrud pariatur aliqua.
    EOF;
$string2 =
    <<<EOF
    Aliquip mollit elit consectetur nulla laborum minim amet.
    EOF;

PHP,
                <<<'PHP'
<?php
$string1 = <<<EOF
Enim Lorem nostrud pariatur aliqua.
EOF;
$string2 =
<<<EOF
Aliquip mollit elit consectetur nulla laborum minim amet.
EOF;
PHP,
                $formatterB
                    ->heredocIndent(HeredocIndent::MIXED),
            ],
            'HANGING' => [
                <<<'PHP'
<?php
$string1 = <<<EOF
    Enim Lorem nostrud pariatur aliqua.
    EOF;
$string2 =
    <<<EOF
        Aliquip mollit elit consectetur nulla laborum minim amet.
        EOF;

PHP,
                <<<'PHP'
<?php
$string1 = <<<EOF
Enim Lorem nostrud pariatur aliqua.
EOF;
$string2 =
<<<EOF
Aliquip mollit elit consectetur nulla laborum minim amet.
EOF;
PHP,
                $formatterB
                    ->heredocIndent(HeredocIndent::HANGING),
            ],
        ];
    }
}
