<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

final class ControlStructureSpacingTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function outputProvider(): array
    {
        return [
            'suppress empty line before closing while' => [
                <<<'PHP'
<?php
do
    something();
while (false);

PHP,
                <<<'PHP'
<?php
do
something();

while (false);
PHP,
            ],
        ];
    }
}
