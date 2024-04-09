<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

final class OperatorSpacingTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code);
    }

    /**
     * @return array<string[]>
     */
    public static function outputProvider(): array
    {
        return [
            [
                <<<'PHP'
<?php
switch (true) {
    case $flags & E_ERROR:
        foo();
        break;

    case $flags & E_WARNING:
        bar();
        break;
}

PHP,
                <<<'PHP'
<?php
switch (true) {
case $flags&E_ERROR:
    foo();
    break;

case $flags&E_WARNING:
    bar();
    break;
}
PHP,
            ],
        ];
    }
}
