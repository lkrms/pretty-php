<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Filter;

use Lkrms\PrettyPHP\Tests\TestCase;

final class NormaliseKeywordsTest extends TestCase
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
function foo()
{
    yield from [];
}

PHP,
                <<<'PHP'
<?php
function foo()
{
    yield   from    [];
}
PHP,
            ],
            [
                <<<'PHP'
<?php
function foo()
{
    YIELD FROM [];
}

PHP,
                <<<'PHP'
<?php
function foo()
{
    YIELD   FROM    [];
}
PHP,
            ],
        ];
    }
}
