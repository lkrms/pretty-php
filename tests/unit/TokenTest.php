<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\PrettyPHP\Catalog\TokenSubId as SubId;
use Lkrms\PrettyPHP\Formatter;
use Salient\Utility\Reflect;

final class TokenTest extends TestCase
{
    /**
     * @requires PHP >= 8.1
     */
    public function testGetColonSubId(): void
    {
        $code = <<<'PHP'
<?php
enum Actions: string
{
    case Run = 'run';
}

$getIndex = $foo
    ? function (int $size, bool $fromZero = true): array {
        return array_fill(start_index: $fromZero ? 0 : 1, count: $size, value: true);
    }
    : $bar;

start:
switch ($argv[1] ?? null):
    case Actions::Run->value:
    case $foo ? fn(): ?int => null : $bar:
        break;
    default:
        exit(1);
endswitch;

while (false)
    unreachable:

do
    reachable:
while (false);
PHP;

        $formatter = (new Formatter())->withDebug();
        $formatter->format($code, \PHP_EOL, null, null, true);

        $actual = [];
        foreach ($formatter->getTokens() ?? [] as $token) {
            if ($token->id === \T_COLON) {
                $actual[] = Reflect::getConstantName(SubId::class, $token->getSubId());
            }
        }

        $this->assertSame([
            'COLON_ENUM_TYPE',
            'COLON_RETURN_TYPE',
            'COLON_NAMED_ARGUMENT',
            'COLON_TERNARY',
            'COLON_NAMED_ARGUMENT',
            'COLON_NAMED_ARGUMENT',
            'COLON_TERNARY',
            'COLON_LABEL',
            'COLON_ALT_SYNTAX',
            'COLON_SWITCH_CASE',
            'COLON_RETURN_TYPE',
            'COLON_TERNARY',
            'COLON_SWITCH_CASE',
            'COLON_SWITCH_CASE',
            'COLON_LABEL',
            'COLON_LABEL',
        ], $actual);
        $this->assertCount(7, array_unique($actual));
    }

    public function testGetQuestionSubId(): void
    {
        $code = <<<'PHP'
<?php
$foo = $bar
    ? function (?int $baz): ?array {
        return $baz === null
            ? null
            : array_fill(0, $baz, true);
    }
    : fn(): ?array =>
        $qux
            ? $quux
            : null;
PHP;

        $formatter = (new Formatter())->withDebug();
        $formatter->format($code, \PHP_EOL, null, null, true);

        $actual = [];
        foreach ($formatter->getTokens() ?? [] as $token) {
            if ($token->id === \T_QUESTION) {
                $actual[] = Reflect::getConstantName(SubId::class, $token->getSubId());
            }
        }

        $this->assertSame([
            'QUESTION_TERNARY',
            'QUESTION_NULLABLE',
            'QUESTION_NULLABLE',
            'QUESTION_TERNARY',
            'QUESTION_NULLABLE',
            'QUESTION_TERNARY',
        ], $actual);
        $this->assertCount(2, array_unique($actual));
    }

    public function testGetUseSubId(): void
    {
        $code = <<<'PHP'
<?php
use Foo\Bar;
use function Foo\baz;
use const Foo\QUX;

class Foo
{
    use Bar;
    use FooBar {
        FooBar::func insteadof Bar;
    }
}

$foo = function () use ($bar) {};
PHP;

        $formatter = (new Formatter())->withDebug();
        $formatter->format($code, \PHP_EOL, null, null, true);

        $actual = [];
        foreach ($formatter->getTokens() ?? [] as $token) {
            if ($token->id === \T_USE) {
                $actual[] = Reflect::getConstantName(SubId::class, $token->getSubId());
            }
        }

        $this->assertSame([
            'USE_IMPORT',
            'USE_IMPORT',
            'USE_IMPORT',
            'USE_TRAIT',
            'USE_TRAIT',
            'USE_VARIABLES',
        ], $actual);
        $this->assertCount(3, array_unique($actual));
    }
}
