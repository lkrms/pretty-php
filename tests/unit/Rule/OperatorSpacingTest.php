<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Tests\TestCase;

final class OperatorSpacingTest extends TestCase
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
            [
                <<<'PHP'
<?php
$foo++ . 'baz';
$foo->Bar++ . 'baz';
$foo->{'bar'}++ . 'baz';
Foo::$Bar++ . 'baz';
$foo['bar']++ . 'baz';
foo()++ . 'baz';
'baz' . --$foo;
'baz' . --($foo)->Bar;
'baz' . --[$foo][0]->{'bar'};
'baz' . --${Foo::$Bar};
'baz' . --array($foo)[0];
'baz' . --foo();

PHP,
                <<<'PHP'
<?php
$foo++.'baz';
$foo->Bar++.'baz';
$foo->{'bar'}++.'baz';
Foo::$Bar++.'baz';
$foo['bar']++.'baz';
foo()++.'baz';
'baz'.--$foo;
'baz'.--($foo)->Bar;
'baz'.--[$foo][0]->{'bar'};
'baz'.--${Foo::$Bar};
'baz'.--array($foo)[0];
'baz'.--foo();
PHP,
            ],
        ];
    }
}
