<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class FormatHeredocsTest extends TestCase
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
        $builder = Formatter::build();

        return [
            [
                <<<'PHP'
<?php
$foo = fn($bar) => $bar;
$baz = true;
echo <<<EOF
    line 1

    {$foo(
        $baz
            ? <<<EOF
                line 3

                line 5

                EOF
            : ''
    )}
    line 7

    EOF;

PHP,
                <<<'PHP'
<?php
$foo = fn($bar) => $bar;
$baz = true;
echo <<<EOF
line 1

{$foo(
$baz
? <<<EOF
line 3

line 5

EOF
: ''
)}
line 7

EOF;
PHP,
                $builder,
            ],
            'NONE' => [
                <<<'PHP'
<?php
$foo = [
    'bar' => <<<EOF
Content
EOF,
];

PHP,
                <<<'PHP'
<?php
$foo = [
'bar' => <<<EOF
Content
EOF,
];
PHP,
                $builder->heredocIndent(HeredocIndent::NONE),
            ],
            'LINE' => [
                <<<'PHP'
<?php
$foo = [
    'bar' => <<<EOF
    Content
    EOF,
];

PHP,
                <<<'PHP'
<?php
$foo = [
'bar' => <<<EOF
Content
EOF,
];
PHP,
                $builder->heredocIndent(HeredocIndent::LINE),
            ],
            'MIXED' => [
                <<<'PHP'
<?php
$foo = <<<EOF
    Content
    EOF;
$bar =
    <<<EOF
    Content
    EOF;

PHP,
                <<<'PHP'
<?php
$foo = <<<EOF
Content
EOF;
$bar =
<<<EOF
Content
EOF;
PHP,
                $builder->heredocIndent(HeredocIndent::MIXED),
            ],
            'HANGING' => [
                <<<'PHP'
<?php
$foo = <<<EOF
    Content
    EOF;
$bar =
    <<<EOF
        Content
        EOF;

PHP,
                <<<'PHP'
<?php
$foo = <<<EOF
Content
EOF;
$bar =
<<<EOF
Content
EOF;
PHP,
                $builder->heredocIndent(HeredocIndent::HANGING),
            ],
        ];
    }
}
