<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Filter\RemoveWhitespace;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\Parser;
use Lkrms\PrettyPHP\TokenIndex;
use Lkrms\PrettyPHP\TokenUtil;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Reflect;
use Salient\Utility\Str;

final class ParserTest extends TestCase
{
    /**
     * @dataProvider parseProvider
     *
     * @param array<array<string,mixed>> $expected
     */
    public function testParse(array $expected, string $code): void
    {
        $formatter = new Formatter();
        $parser = new Parser($formatter);
        $tokens = $parser->parse(Str::eolFromNative($code), new RemoveWhitespace($formatter))->Tokens;
        foreach ($tokens as $token) {
            $actual[] = Arr::unset(
                TokenUtil::serialize($token),
                'Statement',
                'EndStatement',
                'Expression',
                'EndExpression',
            );
        }
        $actualCode = Get::code($actual ?? [], ",\n");
        $this->assertSame(
            $expected,
            $actual ?? [],
            'If $code has changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return array<array{array<array<string,mixed>>,string}>
     */
    public static function parseProvider(): array
    {
        return [
            [
                [
                    [
                        'id' => 'T_OPEN_TAG',
                        'text' => '<?php',
                        'line' => 1,
                        'pos' => 0,
                        'NextSibling' => "T1:L2:'if'",
                        'OriginalText' => "<?php\n",
                    ],
                    [
                        'id' => 'T_IF',
                        'text' => 'if',
                        'line' => 2,
                        'pos' => 6,
                        'NextSibling' => "T2:L2:'('",
                        'Flags' => 'CODE|UNENCLOSED_PARENT',
                    ],
                    [
                        'id' => '(',
                        'text' => '(',
                        'line' => 2,
                        'pos' => 9,
                        'PrevSibling' => "T1:L2:'if'",
                        'NextSibling' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_STRING',
                        'text' => 'true',
                        'line' => 2,
                        'pos' => 10,
                        'Parent' => "T2:L2:'('",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => ')',
                        'text' => ')',
                        'line' => 2,
                        'pos' => 14,
                        'PrevSibling' => "T1:L2:'if'",
                        'NextSibling' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_OPEN_UNENCLOSED',
                        'text' => '',
                        'line' => 2,
                        'pos' => 15,
                        'PrevSibling' => "T2:L2:'('",
                        'NextSibling' => "T19:L7:'else'",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T4:L2:')'",
                            'NEXT_REAL' => "T6:L3:'if'",
                            'BOUND_TO' => "T4:L2:')'",
                            'UNENCLOSED_PARENT' => "T1:L2:'if'",
                            'UNENCLOSED_CONTINUES' => true,
                        ],
                    ],
                    [
                        'id' => 'T_IF',
                        'text' => 'if',
                        'line' => 3,
                        'pos' => 20,
                        'NextSibling' => "T7:L3:'('",
                        'Parent' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => '(',
                        'text' => '(',
                        'line' => 3,
                        'pos' => 23,
                        'PrevSibling' => "T6:L3:'if'",
                        'NextSibling' => "T10:L3:':'",
                        'Parent' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_STRING',
                        'text' => 'false',
                        'line' => 3,
                        'pos' => 24,
                        'Parent' => "T7:L3:'('",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => ')',
                        'text' => ')',
                        'line' => 3,
                        'pos' => 29,
                        'PrevSibling' => "T6:L3:'if'",
                        'NextSibling' => "T10:L3:':'",
                        'Parent' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => ':',
                        'text' => ':',
                        'line' => 3,
                        'pos' => 30,
                        'subId' => 'COLON_ALT_SYNTAX_DELIMITER',
                        'PrevSibling' => "T7:L3:'('",
                        'NextSibling' => "T12:L4:'else'",
                        'Parent' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_END_ALT_SYNTAX',
                        'text' => '',
                        'line' => 4,
                        'pos' => 36,
                        'PrevSibling' => "T7:L3:'('",
                        'NextSibling' => "T12:L4:'else'",
                        'Parent' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T10:L3:':'",
                            'NEXT_REAL' => "T12:L4:'else'",
                            'BOUND_TO' => "T12:L4:'else'",
                        ],
                    ],
                    [
                        'id' => 'T_ELSE',
                        'text' => 'else',
                        'line' => 4,
                        'pos' => 36,
                        'PrevSibling' => "T10:L3:':'",
                        'NextSibling' => "T13:L4:':'",
                        'Parent' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => ':',
                        'text' => ':',
                        'line' => 4,
                        'pos' => 40,
                        'subId' => 'COLON_ALT_SYNTAX_DELIMITER',
                        'PrevSibling' => "T12:L4:'else'",
                        'NextSibling' => "T15:L5:'endif'",
                        'Parent' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_END_ALT_SYNTAX',
                        'text' => '',
                        'line' => 5,
                        'pos' => 46,
                        'PrevSibling' => "T12:L4:'else'",
                        'NextSibling' => "T15:L5:'endif'",
                        'Parent' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T13:L4:':'",
                            'NEXT_REAL' => "T15:L5:'endif'",
                            'BOUND_TO' => "T15:L5:'endif'",
                        ],
                    ],
                    [
                        'id' => 'T_ENDIF',
                        'text' => 'endif',
                        'line' => 5,
                        'pos' => 46,
                        'PrevSibling' => "T13:L4:':'",
                        'NextSibling' => "T16:L6:'?>'",
                        'Parent' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_CLOSE_TAG',
                        'text' => '?>',
                        'line' => 6,
                        'pos' => 52,
                        'PrevSibling' => "T15:L5:'endif'",
                        'Parent' => "T5:L2:')'<<(virtual)",
                        'Flags' => 'CODE|STATEMENT_TERMINATOR',
                    ],
                    [
                        'id' => 'T_OPEN_TAG',
                        'text' => '<?php',
                        'line' => 6,
                        'pos' => 54,
                        'PrevSibling' => "T16:L6:'?>'",
                        'Parent' => "T5:L2:')'<<(virtual)",
                        'OriginalText' => "<?php\n",
                    ],
                    [
                        'id' => 'T_CLOSE_UNENCLOSED',
                        'text' => '',
                        'line' => 7,
                        'pos' => 60,
                        'PrevSibling' => "T2:L2:'('",
                        'NextSibling' => "T19:L7:'else'",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T17:L6:'<?php'",
                            'NEXT_REAL' => "T19:L7:'else'",
                            'BOUND_TO' => "T19:L7:'else'",
                        ],
                    ],
                    [
                        'id' => 'T_ELSE',
                        'text' => 'else',
                        'line' => 7,
                        'pos' => 60,
                        'PrevSibling' => "T5:L2:')'<<(virtual)",
                        'NextSibling' => "T20:L7:'else'<<(virtual)",
                        'Flags' => 'CODE|UNENCLOSED_PARENT',
                    ],
                    [
                        'id' => 'T_OPEN_UNENCLOSED',
                        'text' => '',
                        'line' => 7,
                        'pos' => 64,
                        'PrevSibling' => "T19:L7:'else'",
                        'NextSibling' => "T22:L8:'?>'",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T19:L7:'else'",
                            'NEXT_REAL' => "T22:L8:'?>'",
                            'BOUND_TO' => "T19:L7:'else'",
                            'UNENCLOSED_PARENT' => "T19:L7:'else'",
                            'UNENCLOSED_CONTINUES' => false,
                        ],
                    ],
                    [
                        'id' => 'T_CLOSE_UNENCLOSED',
                        'text' => '',
                        'line' => 7,
                        'pos' => 64,
                        'PrevSibling' => "T19:L7:'else'",
                        'NextSibling' => "T22:L8:'?>'",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T19:L7:'else'",
                            'NEXT_REAL' => "T22:L8:'?>'",
                            'BOUND_TO' => "T19:L7:'else'",
                        ],
                    ],
                    [
                        'id' => 'T_CLOSE_TAG',
                        'text' => '?>',
                        'line' => 8,
                        'pos' => 65,
                        'PrevSibling' => "T20:L7:'else'<<(virtual)",
                        'Flags' => 'CODE|STATEMENT_TERMINATOR',
                    ],
                    [
                        'id' => 'T_OPEN_TAG',
                        'text' => '<?php',
                        'line' => 8,
                        'pos' => 67,
                        'PrevSibling' => "T22:L8:'?>'",
                    ],
                ],
                <<<'PHP'
<?php
if (true)
    if (false):
    else:
    endif
?><?php
else
?><?php
PHP,
            ],
            [
                [
                    [
                        'id' => 'T_OPEN_TAG',
                        'text' => '<?php',
                        'line' => 1,
                        'pos' => 0,
                        'NextSibling' => "T2:L2:'if'",
                        'OriginalText' => '<?php ',
                    ],
                    [
                        'id' => 'T_COMMENT',
                        'text' => '// comment',
                        'line' => 1,
                        'pos' => 7,
                        'NextSibling' => "T2:L2:'if'",
                        'Flags' => 'ONELINE_COMMENT|CPP_COMMENT',
                    ],
                    [
                        'id' => 'T_IF',
                        'text' => 'if',
                        'line' => 2,
                        'pos' => 18,
                        'NextSibling' => "T3:L2:'('",
                        'Flags' => 'CODE|UNENCLOSED_PARENT',
                    ],
                    [
                        'id' => '(',
                        'text' => '(',
                        'line' => 2,
                        'pos' => 21,
                        'PrevSibling' => "T2:L2:'if'",
                        'NextSibling' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_STRING',
                        'text' => 'true',
                        'line' => 2,
                        'pos' => 22,
                        'Parent' => "T3:L2:'('",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => ')',
                        'text' => ')',
                        'line' => 2,
                        'pos' => 26,
                        'PrevSibling' => "T2:L2:'if'",
                        'NextSibling' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_OPEN_UNENCLOSED',
                        'text' => '',
                        'line' => 2,
                        'pos' => 27,
                        'PrevSibling' => "T3:L2:'('",
                        'NextSibling' => "T25:L7:'else'",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T5:L2:')'",
                            'NEXT_REAL' => "T7:L2:'// comment'",
                            'BOUND_TO' => "T5:L2:')'",
                            'UNENCLOSED_PARENT' => "T2:L2:'if'",
                            'UNENCLOSED_CONTINUES' => true,
                        ],
                    ],
                    [
                        'id' => 'T_COMMENT',
                        'text' => '// comment',
                        'line' => 2,
                        'pos' => 29,
                        'NextSibling' => "T8:L3:'if'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'ONELINE_COMMENT|CPP_COMMENT',
                    ],
                    [
                        'id' => 'T_IF',
                        'text' => 'if',
                        'line' => 3,
                        'pos' => 44,
                        'NextSibling' => "T9:L3:'('",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => '(',
                        'text' => '(',
                        'line' => 3,
                        'pos' => 47,
                        'PrevSibling' => "T8:L3:'if'",
                        'NextSibling' => "T12:L3:':'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_STRING',
                        'text' => 'false',
                        'line' => 3,
                        'pos' => 48,
                        'Parent' => "T9:L3:'('",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => ')',
                        'text' => ')',
                        'line' => 3,
                        'pos' => 53,
                        'PrevSibling' => "T8:L3:'if'",
                        'NextSibling' => "T12:L3:':'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => ':',
                        'text' => ':',
                        'line' => 3,
                        'pos' => 54,
                        'subId' => 'COLON_ALT_SYNTAX_DELIMITER',
                        'PrevSibling' => "T9:L3:'('",
                        'NextSibling' => "T15:L4:'else'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_COMMENT',
                        'text' => '// comment',
                        'line' => 3,
                        'pos' => 57,
                        'Parent' => "T12:L3:':'",
                        'Flags' => 'ONELINE_COMMENT|CPP_COMMENT',
                    ],
                    [
                        'id' => 'T_END_ALT_SYNTAX',
                        'text' => '',
                        'line' => 4,
                        'pos' => 72,
                        'PrevSibling' => "T9:L3:'('",
                        'NextSibling' => "T15:L4:'else'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T13:L3:'// comment'",
                            'NEXT_REAL' => "T15:L4:'else'",
                            'BOUND_TO' => "T15:L4:'else'",
                        ],
                    ],
                    [
                        'id' => 'T_ELSE',
                        'text' => 'else',
                        'line' => 4,
                        'pos' => 72,
                        'PrevSibling' => "T12:L3:':'",
                        'NextSibling' => "T16:L4:':'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => ':',
                        'text' => ':',
                        'line' => 4,
                        'pos' => 76,
                        'subId' => 'COLON_ALT_SYNTAX_DELIMITER',
                        'PrevSibling' => "T15:L4:'else'",
                        'NextSibling' => "T19:L5:'endif'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_COMMENT',
                        'text' => '// comment',
                        'line' => 4,
                        'pos' => 79,
                        'Parent' => "T16:L4:':'",
                        'Flags' => 'ONELINE_COMMENT|CPP_COMMENT',
                    ],
                    [
                        'id' => 'T_END_ALT_SYNTAX',
                        'text' => '',
                        'line' => 5,
                        'pos' => 94,
                        'PrevSibling' => "T15:L4:'else'",
                        'NextSibling' => "T19:L5:'endif'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T17:L4:'// comment'",
                            'NEXT_REAL' => "T19:L5:'endif'",
                            'BOUND_TO' => "T19:L5:'endif'",
                        ],
                    ],
                    [
                        'id' => 'T_ENDIF',
                        'text' => 'endif',
                        'line' => 5,
                        'pos' => 94,
                        'PrevSibling' => "T16:L4:':'",
                        'NextSibling' => "T21:L6:'?>'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE',
                    ],
                    [
                        'id' => 'T_COMMENT',
                        'text' => '// comment',
                        'line' => 5,
                        'pos' => 101,
                        'PrevSibling' => "T19:L5:'endif'",
                        'NextSibling' => "T21:L6:'?>'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'ONELINE_COMMENT|CPP_COMMENT',
                    ],
                    [
                        'id' => 'T_CLOSE_TAG',
                        'text' => '?>',
                        'line' => 6,
                        'pos' => 112,
                        'PrevSibling' => "T19:L5:'endif'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'Flags' => 'CODE|STATEMENT_TERMINATOR',
                    ],
                    [
                        'id' => 'T_OPEN_TAG',
                        'text' => '<?php',
                        'line' => 6,
                        'pos' => 114,
                        'PrevSibling' => "T21:L6:'?>'",
                        'Parent' => "T6:L2:')'<<(virtual)",
                        'OriginalText' => '<?php ',
                    ],
                    [
                        'id' => 'T_CLOSE_UNENCLOSED',
                        'text' => '',
                        'line' => 6,
                        'pos' => 121,
                        'PrevSibling' => "T3:L2:'('",
                        'NextSibling' => "T25:L7:'else'",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T22:L6:'<?php'",
                            'NEXT_REAL' => "T24:L6:'// comment'",
                            'BOUND_TO' => "T24:L6:'// comment'",
                        ],
                    ],
                    [
                        'id' => 'T_COMMENT',
                        'text' => '// comment',
                        'line' => 6,
                        'pos' => 121,
                        'PrevSibling' => "T6:L2:')'<<(virtual)",
                        'NextSibling' => "T25:L7:'else'",
                        'Flags' => 'ONELINE_COMMENT|CPP_COMMENT',
                    ],
                    [
                        'id' => 'T_ELSE',
                        'text' => 'else',
                        'line' => 7,
                        'pos' => 132,
                        'PrevSibling' => "T6:L2:')'<<(virtual)",
                        'NextSibling' => "T26:L7:'else'<<(virtual)",
                        'Flags' => 'CODE|UNENCLOSED_PARENT',
                    ],
                    [
                        'id' => 'T_OPEN_UNENCLOSED',
                        'text' => '',
                        'line' => 7,
                        'pos' => 136,
                        'PrevSibling' => "T25:L7:'else'",
                        'NextSibling' => "T29:L8:'?>'",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T25:L7:'else'",
                            'NEXT_REAL' => "T27:L7:'// comment'",
                            'BOUND_TO' => "T25:L7:'else'",
                            'UNENCLOSED_PARENT' => "T25:L7:'else'",
                            'UNENCLOSED_CONTINUES' => false,
                        ],
                    ],
                    [
                        'id' => 'T_COMMENT',
                        'text' => '// comment',
                        'line' => 7,
                        'pos' => 138,
                        'Parent' => "T26:L7:'else'<<(virtual)",
                        'Flags' => 'ONELINE_COMMENT|CPP_COMMENT',
                    ],
                    [
                        'id' => 'T_CLOSE_UNENCLOSED',
                        'text' => '',
                        'line' => 7,
                        'pos' => 148,
                        'PrevSibling' => "T25:L7:'else'",
                        'NextSibling' => "T29:L8:'?>'",
                        'Flags' => 'CODE',
                        'Data' => [
                            'PREV_REAL' => "T27:L7:'// comment'",
                            'NEXT_REAL' => "T29:L8:'?>'",
                            'BOUND_TO' => "T27:L7:'// comment'",
                        ],
                    ],
                    [
                        'id' => 'T_CLOSE_TAG',
                        'text' => '?>',
                        'line' => 8,
                        'pos' => 149,
                        'PrevSibling' => "T26:L7:'else'<<(virtual)",
                        'Flags' => 'CODE|STATEMENT_TERMINATOR',
                    ],
                    [
                        'id' => 'T_OPEN_TAG',
                        'text' => '<?php',
                        'line' => 8,
                        'pos' => 151,
                        'PrevSibling' => "T29:L8:'?>'",
                        'OriginalText' => '<?php ',
                    ],
                    [
                        'id' => 'T_COMMENT',
                        'text' => '// comment',
                        'line' => 8,
                        'pos' => 158,
                        'PrevSibling' => "T29:L8:'?>'",
                        'Flags' => 'ONELINE_COMMENT|CPP_COMMENT',
                    ],
                ],
                <<<'PHP'
<?php  // comment
if (true)  // comment
    if (false):  // comment
    else:  // comment
    endif  // comment
?><?php  // comment
else  // comment
?><?php  // comment
PHP,
            ],
        ];
    }

    /**
     * @dataProvider statementsProvider
     *
     * @param string[] $expected
     */
    public function testStatements(array $expected, string $code): void
    {
        $formatter = new Formatter();
        $parser = new Parser($formatter);
        $statements = $parser->parse($code, new RemoveWhitespace($formatter))->Statements;
        foreach ($statements as $token) {
            $this->assertNotNull($token->EndStatement);
            if ($token === $token->EndStatement) {
                $actual[] = TokenUtil::describe($token);
            } else {
                $actual[] = sprintf(
                    '%s - %s',
                    TokenUtil::describe($token),
                    TokenUtil::describe($token->EndStatement),
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
                    "T1:L2:'for' - T25:L3:'}'",
                    "T3:L2:'\$i' - T6:L2:','",
                    "T7:L2:'\$j' - T10:L2:';'",
                    "T11:L2:'\$i' - T17:L2:';'",
                    "T15:L2:'\$foo'",
                    "T18:L2:'\$i' - T20:L2:','",
                    "T21:L2:'\$j' - T22:L2:'--'",
                ],
                <<<'PHP'
<?php
for ($i = 0, $j = 0; $i < count($foo); $i++, $j--) {
}
PHP,
            ],
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
            [
                [
                    "T1:L2:'if' - T18:L4:';'<<(virtual)",
                    "T3:L2:'\$foo'",
                    "T6:L2:';'",
                    "T10:L3:'\$bar'",
                    "T13:L3:';'",
                    "T17:L4:';'",
                    "T19:L6:'do' - T29:L7:';'",
                    "T21:L6:';'",
                    "T25:L7:'foo' - T27:L7:')'",
                    "T30:L9:'while' - T38:L9:';'<<(virtual)",
                    "T32:L9:'foo' - T34:L9:')'",
                    "T37:L9:';'",
                    "T39:L11:'for' - T46:L11:';'<<(virtual)",
                    "T41:L11:';'",
                    "T42:L11:';'",
                    "T45:L11:';'",
                    "T47:L13:'foreach' - T55:L13:';'<<(virtual)",
                    "T49:L13:'\$foo' - T51:L13:'\$bar'",
                    "T54:L13:';'",
                    "T56:L15:'if' - T82:L20:';'<<(virtual)",
                    "T58:L15:'\$foo'",
                    "T61:L16:'bar' - T64:L16:';'",
                    "T68:L17:'\$baz'",
                    "T71:L18:'qux' - T74:L18:';'",
                    "T78:L20:'quux' - T81:L20:';'",
                    "T83:L22:'do' - T96:L24:';'",
                    "T85:L23:'foo' - T88:L23:';'",
                    "T92:L24:'bar' - T94:L24:')'",
                    "T97:L26:'do' - T115:L28:';'",
                    "T99:L27:'while' - T107:L27:';'<<(virtual)",
                    "T101:L27:'foo' - T103:L27:')'",
                    "T106:L27:';'",
                    "T111:L28:'bar' - T113:L28:')'",
                    "T116:L30:'while' - T127:L31:';'<<(virtual)",
                    "T118:L30:'foo' - T120:L30:')'",
                    "T123:L31:'bar' - T126:L31:';'",
                    "T128:L33:'for' - T138:L34:';'<<(virtual)",
                    "T130:L33:';'",
                    "T131:L33:';'",
                    "T134:L34:'foo' - T137:L34:';'",
                    "T139:L36:'foreach' - T150:L37:';'<<(virtual)",
                    "T141:L36:'\$foo' - T143:L36:'\$bar'",
                    "T146:L37:'baz' - T149:L37:';'",
                ],
                <<<'PHP'
<?php
if ($foo);
elseif ($bar);
else;

do;
while (foo());

while (foo());

for (;;);

foreach ($foo as $bar);

if ($foo)
    bar();
elseif ($baz)
    qux();
else
    quux();

do
    foo();
while (bar());

do
    while (foo());
while (bar());

while (foo())
    bar();

for (;;)
    foo();

foreach ($foo as $bar)
    baz();
PHP,
            ],
            [
                [
                    "T1:L2:'if' - T21:L7:':'<<(virtual)",
                    "T3:L2:'\$foo'",
                    "T6:L3:'label' - T7:L3:':'",
                    "T11:L4:'\$bar'",
                    "T14:L5:'label' - T15:L5:':'",
                    "T19:L7:'label' - T20:L7:':'",
                    "T22:L9:'do' - T33:L11:';'",
                    "T24:L10:'label' - T25:L10:':'",
                    "T29:L11:'foo' - T31:L11:')'",
                    "T34:L13:'while' - T43:L14:':'<<(virtual)",
                    "T36:L13:'foo' - T38:L13:')'",
                    "T41:L14:'label' - T42:L14:':'",
                    "T44:L16:'for' - T52:L17:':'<<(virtual)",
                    "T46:L16:';'",
                    "T47:L16:';'",
                    "T50:L17:'label' - T51:L17:':'",
                    "T53:L19:'foreach' - T62:L20:':'<<(virtual)",
                    "T55:L19:'\$foo' - T57:L19:'\$bar'",
                    "T60:L20:'label' - T61:L20:':'",
                ],
                <<<'PHP'
<?php
if ($foo)
    label:
elseif ($bar)
    label:
else
    label:

do
    label:
while (foo());

while (foo())
    label:

for (;;)
    label:

foreach ($foo as $bar)
    label:
PHP,
            ],
            [
                [
                    "T1:L2:'if' - T27:L8:'}'",
                    "T3:L2:'\$foo'",
                    "T6:L3:'bar' - T9:L3:';'",
                    "T13:L4:'\$baz'",
                    "T16:L5:'qux' - T19:L5:';'",
                    "T23:L7:'quux' - T26:L7:';'",
                    "T28:L10:'do' - T41:L12:';'",
                    "T30:L11:'foo' - T33:L11:';'",
                    "T37:L12:'bar' - T39:L12:')'",
                    "T42:L14:'while' - T53:L16:'}'",
                    "T44:L14:'foo' - T46:L14:')'",
                    "T49:L15:'bar' - T52:L15:';'",
                    "T54:L18:'for' - T64:L20:'}'",
                    "T56:L18:';'",
                    "T57:L18:';'",
                    "T60:L19:'foo' - T63:L19:';'",
                    "T65:L22:'foreach' - T76:L24:'}'",
                    "T67:L22:'\$foo' - T69:L22:'\$bar'",
                    "T72:L23:'baz' - T75:L23:';'",
                ],
                <<<'PHP'
<?php
if ($foo) {
    bar();
} elseif ($baz) {
    qux();
} else {
    quux();
}

do {
    foo();
} while (bar());

while (foo()) {
    bar();
}

for (;;) {
    foo();
}

foreach ($foo as $bar) {
    baz();
}
PHP,
            ],
            [
                [
                    "T1:L2:'do' - T41:L11:';'",
                    "T3:L3:'do' - T33:L10:';'",
                    "T5:L4:'if' - T24:L9:'?>'",
                    "T7:L4:'\$foo'",
                    "T10:L5:'?>'",
                    "T15:L6:'\$bar'",
                    "T18:L7:'?>'",
                    "T29:L10:'baz' - T31:L10:')'",
                    "T37:L11:'qux' - T39:L11:')'",
                    "T42:L13:'do' - T59:L16:';'",
                    "T44:L14:'if' - T50:L15:'?>'",
                    "T46:L14:'\$foo'",
                    "T55:L16:'bar' - T57:L16:')'",
                    "T60:L18:'while' - T74:L20:'?>'",
                    "T62:L18:'foo' - T64:L18:')'",
                    "T67:L19:'if' - T72:L19:')'<<(virtual)",
                    "T69:L19:'\$bar'",
                ],
                <<<'PHP'
<?php
do
    do
        if ($foo)
?><?php
        elseif ($bar)
?><?php
        else
?><?php
    while (baz());
while (qux());

do
    if ($foo)
?><?php
while (bar());

while (foo())
    if ($bar)
?><?php
PHP,
            ],
            [
                [
                    "T1:L2:'if' - T7:L2:'?>'",
                    "T3:L2:'\$foo'",
                ],
                <<<'PHP'
<?php
if ($foo) ?>bar
PHP,
            ],
            [
                [
                    "T1:L2:'if' - T10:L3:'?>'",
                    "T3:L2:'\$foo'",
                    "T6:L3:'bar' - T8:L3:')'",
                ],
                <<<'PHP'
<?php
if ($foo)
    bar() ?>baz
PHP,
            ],
        ];
    }

    /**
     * @dataProvider declarationsProvider
     *
     * @param array<array{string,string,int}> $expected
     */
    public function testDeclarations(array $expected, string $code): void
    {
        $formatter = new Formatter();
        $parser = new Parser($formatter);
        $declarations = $parser->parse($code, new RemoveWhitespace($formatter))->Declarations;
        foreach ($declarations as $token) {
            $this->assertNotNull($token->EndStatement);
            $data = [];
            if ($token === $token->EndStatement) {
                $data[] = TokenUtil::describe($token);
            } else {
                $data[] = sprintf(
                    '%s - %s',
                    TokenUtil::describe($token),
                    TokenUtil::describe($token->EndStatement),
                );
            }
            $data[] = $token->Data[TokenData::NAMED_DECLARATION_PARTS]->toString(' ');
            $data[] = $type = $token->Data[TokenData::NAMED_DECLARATION_TYPE];
            $actual[] = $data;

            $type = 'Type::' . Reflect::getConstantName(Type::class, $type);
            $data[2] = $type;
            $constants[$type] = $type;
            $actualCode[] = $data;
        }

        $actualCode = Get::code($actualCode ?? [], ",\n", ' => ', null, '    ', [], $constants ?? []);
        $this->assertSame(
            $expected,
            $actual ?? [],
            'If $code has changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return iterable<array{array<array{string,string,int}>,string}>
     */
    public static function declarationsProvider(): iterable
    {
        yield from [
            [
                [
                    [
                        "T1:L1:'declare' - T7:L1:';'",
                        'declare',
                        Type::_DECLARE,
                    ],
                    [
                        "T8:L2:'namespace' - T10:L2:';'",
                        'namespace Foo\Bar',
                        Type::_NAMESPACE,
                    ],
                    [
                        "T11:L3:'use' - T13:L3:';'",
                        'use Baz\Factory',
                        Type::_USE,
                    ],
                    [
                        "T14:L4:'use' - T17:L4:';'",
                        'use function in_array',
                        Type::USE_FUNCTION,
                    ],
                    [
                        "T18:L5:'use' - T21:L5:';'",
                        'use const PREG_UNMATCHED_AS_NULL',
                        Type::USE_CONST,
                    ],
                    [
                        "T22:L6:'class' - T88:L23:'}'",
                        'class Foo',
                        Type::_CLASS,
                    ],
                    [
                        "T25:L7:'use' - T27:L7:';'",
                        'use Factory',
                        Type::USE_TRAIT,
                    ],
                    [
                        "T28:L8:'static' - T30:L8:';'",
                        'static',
                        Type::PROPERTY,
                    ],
                    [
                        "T31:L9:'static' - T36:L9:';'",
                        'static int',
                        Type::PROPERTY,
                    ],
                    [
                        "T37:L10:'public' - T43:L10:';'",
                        'public',
                        Type::PROPERTY,
                    ],
                    [
                        "T44:L11:'public' - T57:L13:'}'",
                        'public function __construct',
                        Type::_FUNCTION,
                    ],
                    [
                        "T58:L14:'static' - T87:L22:'}'",
                        'static public function foo',
                        Type::_FUNCTION,
                    ],
                    [
                        "T89:L24:'function' - T97:L26:'}'",
                        'function foo',
                        Type::_FUNCTION,
                    ],
                ],
                <<<'PHP'
<?php declare(strict_types=1);
namespace Foo\Bar;
use Baz\Factory;
use function in_array;
use const PREG_UNMATCHED_AS_NULL;
class Foo {
    use Factory;
    static $Bar;
    static int $Baz = 0;
    public array $Qux = [];
    public function __construct(string $qux) {
        static::$Baz++;
    }
    static public function foo() {
        switch (static::$Baz) {
            case 0:
                break;
            default:
                static::$Baz--;
                break;
        }
    }
}
function foo() {
    static $bar;
}
PHP,
            ],
            [
                [
                    [
                        "T6:L3:'public' - T15:L5:'}'",
                        'public function __toString',
                        Type::_FUNCTION,
                    ],
                ],
                <<<'PHP'
<?php
new class() {
    public function __toString() {
        return 'foo';
    }
};
function () {};
static function () {};
PHP,
            ],
        ];

        if (\PHP_VERSION_ID < 80000) {
            return;
        }

        yield [
            [
                [
                    "T1:L2:'class' - T20:L4:'}'",
                    'class Point',
                    Type::_CLASS,
                ],
                [
                    "T4:L3:'public' - T19:L3:'}'",
                    'public function __construct',
                    Type::_FUNCTION,
                ],
                [
                    "T8:L3:'protected' - T11:L3:','",
                    'protected int',
                    Type::PARAM,
                ],
                [
                    "T12:L3:'protected' - T16:L3:'0'",
                    'protected int',
                    Type::PARAM,
                ],
            ],
            <<<'PHP'
<?php
class Point {
    public function __construct(protected int $x, protected int $y = 0) {}
}
PHP,
        ];

        if (\PHP_VERSION_ID < 80400) {
            return;
        }

        yield [
            [
                [
                    "T1:L2:'class' - T59:L19:'}'",
                    'class Test',
                    Type::_CLASS,
                ],
                [
                    "T4:L3:'public' - T19:L6:'}'",
                    'public',
                    Type::PROPERTY,
                ],
                [
                    "T7:L4:'get' - T12:L4:'}'",
                    'get',
                    Type::HOOK,
                ],
                [
                    "T13:L5:'set' - T18:L5:'}'",
                    'set',
                    Type::HOOK,
                ],
                [
                    "T20:L7:'private' - T31:L10:'}'",
                    'private',
                    Type::PROPERTY,
                ],
                [
                    "T23:L8:'get' - T26:L8:';'",
                    'get',
                    Type::HOOK,
                ],
                [
                    "T27:L9:'set' - T30:L9:';'",
                    'set',
                    Type::HOOK,
                ],
                [
                    "T32:L11:'abstract' - T40:L14:'}'",
                    'abstract',
                    Type::PROPERTY,
                ],
                [
                    "T35:L12:'&' - T37:L12:';'",
                    '& get',
                    Type::HOOK,
                ],
                [
                    "T38:L13:'set' - T39:L13:';'",
                    'set',
                    Type::HOOK,
                ],
                [
                    "T41:L15:'public' - T58:L18:'}'",
                    'public',
                    Type::PROPERTY,
                ],
                [
                    "T44:L16:'final' - T50:L16:'}'",
                    'final get',
                    Type::HOOK,
                ],
                [
                    "T51:L17:'set' - T57:L17:'}'",
                    'set',
                    Type::HOOK,
                ],
            ],
            <<<'PHP'
<?php
class Test {
    public $prop {
        get { return 42; }
        set { echo $value; }
    }
    private $prop2 {
        get => 42;
        set => $value;
    }
    abstract $prop3 {
        &get;
        set;
    }
    public $prop4 {
        final get { return 42; }
        set(string $value) { }
    }
}
PHP,
        ];
    }

    /**
     * @dataProvider expressionsProvider
     *
     * @param string[] $expected
     */
    public function testExpressions(array $expected, string $code): void
    {
        $formatter = new Formatter();
        $parser = new Parser($formatter);
        $tokens = $parser->parse($code, new RemoveWhitespace($formatter))->Tokens;
        foreach ($tokens as $token) {
            if ($token !== $token->Expression) {
                continue;
            }
            $this->assertNotNull($token->EndExpression);
            if ($token === $token->EndExpression) {
                $actual[] = TokenUtil::describe($token);
            } else {
                $actual[] = sprintf(
                    '%s - %s',
                    TokenUtil::describe($token),
                    TokenUtil::describe($token->EndExpression),
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
     * @return iterable<array{string[],string}>
     */
    public static function expressionsProvider(): iterable
    {
        yield from [
            [
                [
                    "T1:L2:'class' - T87:L14:'}'",
                    "T6:L3:'private' - T8:L3:'BAZ'",
                    "T10:L3:'[' - T23:L6:'BAZ'",
                    "T11:L4:'\'foo\''",
                    "T13:L5:'\'BAR\''",
                    "T15:L5:'\'bar\'' - T17:L5:'\'baz\''",
                    "T25:L7:'public' - T86:L13:'}'",
                    "T29:L7:'string' - T30:L7:'\$baz'",
                    "T32:L7:'?' - T35:L7:'\$qux'",
                    "T37:L7:'null'",
                    "T43:L8:'if' - T68:L10:'}'",
                    "T45:L8:'(' - T54:L8:')'",
                    "T46:L8:'self' - T53:L8:'null'",
                    "T50:L8:'\$baz'",
                    "T56:L8:'null'",
                    "T59:L9:'\$qux'",
                    "T61:L9:'self' - T66:L9:']'",
                    "T65:L9:'\$baz'",
                    "T69:L11:'\$foo'",
                    "T71:L11:'\$a'",
                    "T73:L11:'\$b'",
                    "T75:L11:'\$c' - T77:L11:'\$d'",
                    "T79:L11:'\$e'",
                    "T81:L11:'\$f'",
                    "T83:L12:'return' - T84:L12:'\$qux'",
                ],
                <<<'PHP'
<?php
class Foo extends Bar {
    private const BAZ = [
        'foo',
        'BAR' => 'bar' . 'baz',
    ] + parent::BAZ;
    public function bar(string $baz, ?string &$qux = null): ?string {
        if ((self::BAZ[$baz] ?? null) !== null) {
            $qux .= self::BAZ[$baz];
        }
        $foo = $a ? $b ? $c ?? $d : $e : $f;
        return $qux;
    }
}
PHP,
            ],
            [
                [
                    "T1:L2:'\$foo'",
                    "T3:L2:'fn' - T8:L2:'int'",
                    "T10:L2:'null'",
                    "T12:L3:'\$bar'",
                    "T14:L3:'function' - T24:L5:'}'",
                    "T21:L4:'return' - T22:L4:'null'",
                ],
                <<<'PHP'
<?php
$foo = fn(): ?int => null;
$bar = function (): ?int {
    return null;
};
PHP,
            ],
        ];

        if (\PHP_VERSION_ID < 80200) {
            return;
        }

        yield [
            [
                "T1:L2:'\$foo'",
                "T3:L2:'fn' - T13:L2:')'",
                "T10:L2:'Foo' - T12:L2:'Bar'",
                "T15:L2:'\$foo'",
                "T17:L3:'\$bar'",
                "T19:L3:'function' - T34:L5:'}'",
                "T26:L3:'Foo' - T28:L3:'Bar'",
                "T31:L4:'return' - T32:L4:'\$foo'",
            ],
            <<<'PHP'
<?php
$foo = fn(): Baz|(Foo&Bar) => $foo;
$bar = function (): Baz|(Foo&Bar) {
  return $foo;
};
PHP,
        ];
    }

    public function testDeclarationMap(): void
    {
        $idx = array_filter((new TokenIndex())->DeclarationExceptModifierOrVar);
        $map = self::getDeclarationMap();
        $this->assertEmpty(array_diff_key($idx, $map), sprintf(
            '%s::DECLARATION_MAP does not cover %s::$DeclarationExceptModifierOrVar',
            Parser::class,
            TokenIndex::class,
        ));
        $this->assertEmpty(array_diff_key($map, $idx), sprintf(
            '%s::DECLARATION_MAP covers tokens not in %s::$DeclarationExceptModifierOrVar',
            Parser::class,
            TokenIndex::class,
        ));
    }

    /**
     * @return array<int,int>
     */
    private static function getDeclarationMap(): array
    {
        /**
         * @disregard P1012
         * @phpstan-ignore classConstant.notFound
         */
        return (static fn() => self::DECLARATION_MAP)
                   ->bindTo(null, Parser::class)();
    }
}
