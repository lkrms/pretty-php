<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Filter\RemoveWhitespace;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\Parser;
use Lkrms\PrettyPHP\TokenUtil;
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
        ];
    }

    /**
     * @dataProvider declarationsProvider
     *
     * @param array<array<string,string>> $expected
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
                $data['tok'] = TokenUtil::describe($token);
            } else {
                $data['tok'] = sprintf(
                    '%s - %s',
                    TokenUtil::describe($token),
                    TokenUtil::describe($token->EndStatement),
                );
            }
            $data['par'] = $token->Data[TokenData::NAMED_DECLARATION_PARTS]->toString(' ');
            $data['typ'] = implode(',', self::getTokenNames($token->Data[TokenData::NAMED_DECLARATION_TYPE]));
            $actual[] = $data;
        }
        $actualCode = Get::code($actual ?? [], ",\n");
        $this->assertSame(
            $expected,
            $actual ?? [],
            'If $code has changed, replace $expected with: ' . $actualCode,
        );
    }

    /**
     * @return iterable<array{array<array<string,string>>,string}>
     */
    public static function declarationsProvider(): iterable
    {
        yield from [
            [
                [
                    [
                        'tok' => "T1:L1:'declare' - T7:L1:';'",
                        'par' => 'declare',
                        'typ' => 'T_DECLARE',
                    ],
                    [
                        'tok' => "T8:L2:'namespace' - T10:L2:';'",
                        'par' => 'namespace Foo\Bar',
                        'typ' => 'T_NAMESPACE',
                    ],
                    [
                        'tok' => "T11:L3:'use' - T13:L3:';'",
                        'par' => 'use Baz\Factory',
                        'typ' => 'T_USE',
                    ],
                    [
                        'tok' => "T14:L4:'use' - T17:L4:';'",
                        'par' => 'use function in_array',
                        'typ' => 'T_USE,T_FUNCTION',
                    ],
                    [
                        'tok' => "T18:L5:'use' - T21:L5:';'",
                        'par' => 'use const PREG_UNMATCHED_AS_NULL',
                        'typ' => 'T_USE,T_CONST',
                    ],
                    [
                        'tok' => "T22:L6:'class' - T88:L23:'}'",
                        'par' => 'class Foo',
                        'typ' => 'T_CLASS',
                    ],
                    [
                        'tok' => "T25:L7:'use' - T27:L7:';'",
                        'par' => 'use Factory',
                        'typ' => 'T_USE,T_TRAIT',
                    ],
                    [
                        'tok' => "T28:L8:'static' - T30:L8:';'",
                        'par' => 'static',
                        'typ' => 'T_VAR',
                    ],
                    [
                        'tok' => "T31:L9:'static' - T36:L9:';'",
                        'par' => 'static int',
                        'typ' => 'T_VAR',
                    ],
                    [
                        'tok' => "T37:L10:'public' - T43:L10:';'",
                        'par' => 'public',
                        'typ' => 'T_VAR',
                    ],
                    [
                        'tok' => "T44:L11:'public' - T57:L13:'}'",
                        'par' => 'public function __construct',
                        'typ' => 'T_FUNCTION',
                    ],
                    [
                        'tok' => "T58:L14:'static' - T87:L22:'}'",
                        'par' => 'static public function foo',
                        'typ' => 'T_FUNCTION',
                    ],
                    [
                        'tok' => "T89:L24:'function' - T97:L26:'}'",
                        'par' => 'function foo',
                        'typ' => 'T_FUNCTION',
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
                        'tok' => "T6:L3:'public' - T15:L5:'}'",
                        'par' => 'public function __toString',
                        'typ' => 'T_FUNCTION',
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
                    'tok' => "T1:L2:'class' - T20:L4:'}'",
                    'par' => 'class Point',
                    'typ' => 'T_CLASS',
                ],
                [
                    'tok' => "T4:L3:'public' - T19:L3:'}'",
                    'par' => 'public function __construct',
                    'typ' => 'T_FUNCTION',
                ],
                [
                    'tok' => "T8:L3:'protected' - T11:L3:','",
                    'par' => 'protected int',
                    'typ' => 'T_FUNCTION,T_VAR',
                ],
                [
                    'tok' => "T12:L3:'protected' - T16:L3:'0'",
                    'par' => 'protected int',
                    'typ' => 'T_FUNCTION,T_VAR',
                ],
            ],
            <<<'PHP'
<?php
class Point {
    public function __construct(protected int $x, protected int $y = 0) {}
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
}
