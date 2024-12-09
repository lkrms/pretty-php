<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Filter\RemoveWhitespace;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\Parser;
use Lkrms\PrettyPHP\TokenIndex;
use Lkrms\PrettyPHP\TokenUtil;
use Salient\Utility\Get;
use Salient\Utility\Reflect;

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
            [
                [
                    "T1:L2:'if' - T12:L4:';'",
                    "T3:L2:'\$foo'",
                    "T8:L3:'\$bar'",
                    "T13:L6:'do' - T21:L7:';'",
                    "T17:L7:'foo' - T19:L7:')'",
                    "T22:L9:'while' - T28:L9:';'",
                    "T24:L9:'foo' - T26:L9:')'",
                    "T29:L11:'for' - T34:L11:';'",
                    "T31:L11:';'",
                    "T32:L11:';'",
                    "T35:L13:'foreach' - T41:L13:';'",
                    "T37:L13:'\$foo' - T39:L13:'\$bar'",
                    "T42:L15:'if' - T62:L20:';'",
                    "T44:L15:'\$foo'",
                    "T52:L17:'\$baz'",
                    "T63:L22:'do' - T74:L24:';'",
                    "T70:L24:'bar' - T72:L24:')'",
                    "T75:L26:'do' - T89:L28:';'",
                    "T78:L27:'foo' - T80:L27:')'",
                    "T85:L28:'bar' - T87:L28:')'",
                    "T90:L30:'while' - T99:L31:';'",
                    "T92:L30:'foo' - T94:L30:')'",
                    "T100:L33:'for' - T108:L34:';'",
                    "T102:L33:';'",
                    "T103:L33:';'",
                    "T109:L36:'foreach' - T118:L37:';'",
                    "T111:L36:'\$foo' - T113:L36:'\$bar'",
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
