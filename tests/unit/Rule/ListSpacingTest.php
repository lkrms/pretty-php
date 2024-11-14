<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Tests\TestCase;

final class ListSpacingTest extends TestCase
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
            [
                <<<'PHP'
<?php
function getArray()
{
    return [
        'foo',
        'bar',
        'baz',
    ];
}

PHP,
                <<<'PHP'
<?php
function getArray()
{
    return ['foo', 'bar', 'baz',];
}
PHP,
            ],
        ];
    }
}
