<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

final class ControlStructureSpacingTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider processTokenProvider
     */
    public function testProcessToken(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function processTokenProvider(): array
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
