<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignArrowFunctions;
use Lkrms\PrettyPHP\Rule\PreserveNewlines;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class AlignArrowFunctionsTest extends TestCase
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
     * @return iterable<array{string,string,Formatter|FormatterB}>
     */
    public static function outputProvider(): iterable
    {
        $formatterB = Formatter::build()
                          ->enable([AlignArrowFunctions::class]);
        $formatter = $formatterB->build();

        yield from [
            'nested + assigned' => [
                <<<'PHP'
<?php
$foo = bar($baz, fn() =>
                     foo($bar),
    $qux);
$foo = bar($baz, static fn() =>
                     foo($bar),
    $qux);
$foo = fn() =>
           foo($bar);
$foo = fn() =>
           <<<EOF
           baz
           EOF . $bar;

PHP,
                <<<'PHP'
<?php
$foo = bar($baz, fn() =>
        foo($bar),
    $qux);
$foo = bar($baz, static fn() =>
        foo($bar),
    $qux);
$foo = fn() =>
    foo($bar);
$foo = fn() =>
    <<<EOF
    baz
    EOF . $bar;
PHP,
                $formatter,
            ],
        ];

        if (\PHP_VERSION_ID < 80000) {
            return;
        }

        yield from [
            'nested + assigned + attributes' => [
                <<<'PHP'
<?php
$foo = bar($baz, #[Foo] fn() =>
                     foo($bar),
    $qux);
$foo = bar($baz, #[Foo] static fn() =>
                     foo($bar),
    $qux);
$foo = bar($baz,
    #[Foo]
    static fn() =>
        foo($bar),
    $qux);
$foo = #[Foo] fn() =>
           foo($bar);
$foo =
    #[Foo]
    fn() =>
        foo($bar);

PHP,
                <<<'PHP'
<?php
$foo = bar($baz, #[Foo] fn() =>
        foo($bar),
    $qux);
$foo = bar($baz, #[Foo] static fn() =>
        foo($bar),
    $qux);
$foo = bar($baz, #[Foo]
        static fn() =>
            foo($bar),
    $qux);
$foo = #[Foo] fn() =>
    foo($bar);
$foo = #[Foo]
    fn() =>
        foo($bar);
PHP,
                $formatter,
            ],
            'nested + assigned + attributes - PreserveNewlines' => [
                <<<'PHP'
<?php
$foo = bar($baz, #[Foo] fn() => foo($bar), $qux);
$foo = bar($baz, #[Foo] static fn() => foo($bar), $qux);
$foo = bar($baz, #[Foo] static fn() => foo($bar), $qux);
$foo = #[Foo] fn() => foo($bar);
$foo = #[Foo] fn() => foo($bar);

PHP,
                <<<'PHP'
<?php
$foo = bar($baz, #[Foo] fn() =>
        foo($bar),
    $qux);
$foo = bar($baz, #[Foo] static fn() =>
        foo($bar),
    $qux);
$foo = bar($baz, #[Foo]
        static fn() =>
            foo($bar),
    $qux);
$foo = #[Foo] fn() =>
    foo($bar);
$foo = #[Foo]
    fn() =>
        foo($bar);
PHP,
                $formatterB
                    ->withoutExtensions([PreserveNewlines::class]),
            ],
        ];
    }
}
