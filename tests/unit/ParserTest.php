<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\PrettyPHP\Filter\RemoveWhitespace;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\Parser;
use Lkrms\PrettyPHP\TokenUtility;
use Salient\Utility\Get;

final class ParserTest extends TestCase
{
    /**
     * @dataProvider statementsProvider
     *
     * @param string[] $expected
     */
    public function testStatements(array $expected, string $code): void
    {
        $formatter = new Formatter();
        $parser = new Parser($formatter);
        $parser->parse($code, [new RemoveWhitespace($formatter)], $statements);
        foreach ($statements as $token) {
            $this->assertNotNull($token->EndStatement);
            if ($token === $token->EndStatement) {
                $actual[] = TokenUtility::describe($token);
            } else {
                $actual[] = sprintf(
                    '%s - %s',
                    TokenUtility::describe($token),
                    TokenUtility::describe($token->EndStatement),
                );
            }
        }
        $actualCode = Get::code($actual ?? [], ",\n");
        $this->assertSame(
            $expected,
            $actual ?? [],
            'If $code has changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return array<array{string[],string}>
     */
    public static function statementsProvider(): array
    {
        return [
            [
                [
                    "T1:L2:'if' - T29:L8:';'",
                    "T3:L2:'\$foo'",
                    "T6:L3:'foo' - T9:L3:';'",
                    "T13:L4:'\$bar'",
                    "T16:L5:'bar' - T19:L5:';'",
                    "T23:L7:'baz' - T26:L7:';'",
                ],
                <<<'PHP'
<?php
if ($foo):
    foo();
elseif ($bar):
    bar();
else:
    baz();
endif;
PHP,
            ],
            [
                [
                    "T1:L2:'if' - T31:L8:';'",
                    "T3:L2:'\$foo'",
                    "T6:L3:'foo' - T9:L3:';'",
                    "T14:L4:'\$bar'",
                    "T17:L5:'bar' - T20:L5:';'",
                    "T25:L7:'baz' - T28:L7:';'",
                ],
                <<<'PHP'
<?php
if ($foo):
    foo();
elseif /* comment */ ($bar):
    bar();
else /* comment */:
    baz();
endif;
PHP,
            ],
        ];
    }
}
