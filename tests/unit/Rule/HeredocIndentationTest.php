<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Formatter;

final class HeredocIndentationTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     *
     * @param array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null} $options
     */
    public function testOutput(string $expected, string $code, array $options = []): void
    {
        $this->assertFormatterOutputIs($expected, $code, $this->getFormatter($options));
    }

    /**
     * @return array<string,array{string,string,array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null}}>
     */
    public static function outputProvider(): array
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
                ['callback' =>
                    fn(Formatter $f) =>
                        $f->with('HeredocIndent', HeredocIndent::NONE)],
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
                ['callback' =>
                    fn(Formatter $f) =>
                        $f->with('HeredocIndent', HeredocIndent::LINE)],
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
                ['callback' =>
                    fn(Formatter $f) =>
                        $f->with('HeredocIndent', HeredocIndent::MIXED)],
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
                ['callback' =>
                    fn(Formatter $f) =>
                        $f->with('HeredocIndent', HeredocIndent::HANGING)],
            ],
        ];
    }
}
