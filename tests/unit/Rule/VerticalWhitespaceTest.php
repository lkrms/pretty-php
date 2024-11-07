<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Tests\TestCase;

final class VerticalWhitespaceTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code);
    }

    /**
     * @return array<array{string,string}>
     */
    public static function outputProvider(): array
    {
        return [
            'chain #1' => [
                <<<'PHP'
<?php
$foxtrot
    ->foo(
        fn() =>
            bar()
    )
    ->baz()
    ->quux();

PHP,
                <<<'PHP'
<?php
$foxtrot->foo(
fn() =>
bar()
)
->baz()
->quux();
PHP,
            ],
            'chain #2' => [
                <<<'PHP'
<?php
$foxtrot
    ->foo(fn() =>
        bar())
    ->baz()
    ->quux();

PHP,
                <<<'PHP'
<?php
$foxtrot->foo(fn() =>
bar()
)
->baz()
->quux();
PHP,
            ],
        ];
    }
}
