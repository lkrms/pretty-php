<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\PrettyPHP\Catalog\TokenSubId;
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
                $actual[] = Reflect::getConstantName(TokenSubId::class, $token->getSubId());
            }
        }

        $this->assertSame([
            'COLON_BACKED_ENUM_TYPE_DELIMITER',
            'COLON_RETURN_TYPE_DELIMITER',
            'COLON_NAMED_ARGUMENT_DELIMITER',
            'COLON_TERNARY_OPERATOR',
            'COLON_NAMED_ARGUMENT_DELIMITER',
            'COLON_NAMED_ARGUMENT_DELIMITER',
            'COLON_TERNARY_OPERATOR',
            'COLON_LABEL_DELIMITER',
            'COLON_ALT_SYNTAX_DELIMITER',
            'COLON_SWITCH_CASE_DELIMITER',
            'COLON_RETURN_TYPE_DELIMITER',
            'COLON_TERNARY_OPERATOR',
            'COLON_SWITCH_CASE_DELIMITER',
            'COLON_SWITCH_CASE_DELIMITER',
            'COLON_LABEL_DELIMITER',
            'COLON_LABEL_DELIMITER',
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
                $actual[] = Reflect::getConstantName(TokenSubId::class, $token->getSubId());
            }
        }

        $this->assertSame([
            'QUESTION_TERNARY_OPERATOR',
            'QUESTION_NULLABLE',
            'QUESTION_NULLABLE',
            'QUESTION_TERNARY_OPERATOR',
            'QUESTION_NULLABLE',
            'QUESTION_TERNARY_OPERATOR',
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
                $actual[] = Reflect::getConstantName(TokenSubId::class, $token->getSubId());
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
