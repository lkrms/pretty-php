<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Tests\TestCase;

final class PlaceBracesTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code);
    }

    /**
     * @return iterable<array{string,string}>
     */
    public static function outputProvider(): iterable
    {
        if (\PHP_VERSION_ID < 80200) {
            return;
        }

        yield from [
            [
                <<<'PHP'
<?php
function foo(): (Baz&Qux)|null
{
    //
}

PHP,
                <<<'PHP'
<?php
function foo(): (
    Baz&Qux
)|null
{
    //
}
PHP,
            ],
            [
                <<<'PHP'
<?php
function foo(
    $bar
): (Baz&Qux)|null {
    //
}

PHP,
                <<<'PHP'
<?php
function foo(
    $bar
): (
    Baz&Qux
)|null
{
    //
}
PHP,
            ],
            [
                <<<'PHP'
<?php
function foo(): (Baz&Qux)|null {}

PHP,
                <<<'PHP'
<?php
function foo(): (
    Baz&Qux
)|null
{
}
PHP,
            ],
            [
                <<<'PHP'
<?php
function foo(
    $bar
): (Baz&Qux)|null {}

PHP,
                <<<'PHP'
<?php
function foo(
    $bar
): (
    Baz&Qux
)|null
{
}
PHP,
            ],
        ];
    }
}
