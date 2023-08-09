<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

use Lkrms\Pretty\Php\Catalog\HeredocIndent;
use Lkrms\Pretty\Php\Formatter;

final class HeredocIndentationTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider heredocIndentProvider
     *
     * @param array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null} $options
     */
    public function testHeredocIndent(string $expected, string $code, array $options = []): void
    {
        $this->assertFormatterOutputIs($expected, $code, $this->getFormatter($options));
    }

    /**
     * @return array<string,array{string,string,array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null}}>
     */
    public static function heredocIndentProvider(): array
    {
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
                ['callback' => function (Formatter $formatter): Formatter {
                    $formatter->HeredocIndent = HeredocIndent::NONE;
                    return $formatter;
                }],
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
                ['callback' => function (Formatter $formatter): Formatter {
                    $formatter->HeredocIndent = HeredocIndent::LINE;
                    return $formatter;
                }],
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
                ['callback' => function (Formatter $formatter): Formatter {
                    $formatter->HeredocIndent = HeredocIndent::MIXED;
                    return $formatter;
                }],
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
                ['callback' => function (Formatter $formatter): Formatter {
                    $formatter->HeredocIndent = HeredocIndent::HANGING;
                    return $formatter;
                }],
            ],
        ];
    }
}
