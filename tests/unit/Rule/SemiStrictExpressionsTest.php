<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\SemiStrictExpressions;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;
use Lkrms\PrettyPHP\TokenIndex;

final class SemiStrictExpressionsTest extends TestCase
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
        $formatterB = Formatter::build()
                          ->enable([SemiStrictExpressions::class])
                          ->tokenIndex(new TokenIndex(true));
        $formatter = $formatterB->build();

        return [
            [
                <<<'PHP'
<?php
if ($foo || (
    $bar
    && $baz
)) {
    qux();
}
if ($foo || (
    $bar
    && $baz
)) {
    qux();
}
if (
    $foo
    || $bar
    || $baz
) {
    qux();
}

PHP,
                <<<'PHP'
<?php
if ($foo || (
    $bar
    && $baz
)) {
    qux();
}
if ($foo || (
    $bar
    && $baz
)
) {
    qux();
}
if ($foo
        || $bar
        || $baz) {
    qux();
}
PHP,
                $formatter,
            ],
        ];
    }
}
