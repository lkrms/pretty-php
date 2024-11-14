<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\PrettyPHP\TokenTypeIndex;
use Salient\PHPDoc\PHPDoc;
use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Reflect;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Generator;
use LogicException;
use ReflectionClass;
use ReflectionProperty;

class TokenTypeIndexTest extends TestCase
{
    protected const ALWAYS_ALLOWED_AT_START_OR_END = [
        \T_ATTRIBUTE,
        \T_ATTRIBUTE_COMMENT,
        \T_COLON,
        \T_DOUBLE_ARROW,
    ];

    protected const ALWAYS_ALLOWED_AT_START = [
        \T_CLOSE_BRACKET,
        \T_CLOSE_PARENTHESIS,
        \T_CLOSE_TAG,
        \T_LOGICAL_NOT,
        \T_NULLSAFE_OBJECT_OPERATOR,
        \T_OBJECT_OPERATOR,
        \T_QUESTION,
    ];

    protected const ALWAYS_ALLOWED_AT_END = [
        \T_AND_EQUAL,
        \T_CLOSE_BRACE,
        \T_COALESCE_EQUAL,
        \T_COMMA,
        \T_COMMENT,
        \T_CONCAT_EQUAL,
        \T_DIV_EQUAL,
        \T_DOC_COMMENT,
        \T_EQUAL,
        \T_EXTENDS,
        \T_IMPLEMENTS,
        \T_MINUS_EQUAL,
        \T_MOD_EQUAL,
        \T_MUL_EQUAL,
        \T_OPEN_BRACE,
        \T_OPEN_BRACKET,
        \T_OPEN_PARENTHESIS,
        \T_OPEN_TAG,
        \T_OPEN_TAG_WITH_ECHO,
        \T_OR_EQUAL,
        \T_PLUS_EQUAL,
        \T_POW_EQUAL,
        \T_SEMICOLON,
        \T_SL_EQUAL,
        \T_SR_EQUAL,
        \T_XOR_EQUAL,
    ];

    protected const MAYBE_ALLOWED_AT_START = [
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
        \T_AND,
        \T_ATTRIBUTE,
        \T_ATTRIBUTE_COMMENT,
        \T_BOOLEAN_AND,
        \T_BOOLEAN_OR,
        \T_COALESCE,
        \T_COLON,
        \T_CONCAT,
        \T_DIV,
        \T_DOUBLE_ARROW,
        \T_GREATER,
        \T_IS_EQUAL,
        \T_IS_GREATER_OR_EQUAL,
        \T_IS_IDENTICAL,
        \T_IS_NOT_EQUAL,
        \T_IS_NOT_IDENTICAL,
        \T_IS_SMALLER_OR_EQUAL,
        \T_LOGICAL_AND,
        \T_LOGICAL_OR,
        \T_LOGICAL_XOR,
        \T_MINUS,
        \T_MOD,
        \T_MUL,
        \T_NOT,
        \T_OR,
        \T_PLUS,
        \T_POW,
        \T_SL,
        \T_SMALLER,
        \T_SPACESHIP,
        \T_SR,
        \T_XOR,
    ];

    protected const LEADING_OPERATORS = [
        \T_BOOLEAN_AND,
        \T_BOOLEAN_OR,
        \T_GREATER,
        \T_IS_EQUAL,
        \T_IS_GREATER_OR_EQUAL,
        \T_IS_IDENTICAL,
        \T_IS_NOT_EQUAL,
        \T_IS_NOT_IDENTICAL,
        \T_IS_SMALLER_OR_EQUAL,
        \T_LOGICAL_AND,
        \T_LOGICAL_OR,
        \T_LOGICAL_XOR,
        \T_SMALLER,
        \T_SPACESHIP,
    ];

    protected const TRAILING_OPERATORS = [
        \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
        \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
        \T_AND,
        \T_COALESCE,
        \T_CONCAT,
        \T_DIV,
        \T_MINUS,
        \T_MOD,
        \T_MUL,
        \T_NOT,
        \T_OR,
        \T_PLUS,
        \T_POW,
        \T_SL,
        \T_SR,
        \T_XOR,
    ];

    protected const NOT_MOVABLE = [
        \T_ATTRIBUTE,
        \T_ATTRIBUTE_COMMENT,
        \T_DOUBLE_ARROW,
    ];

    /**
     * @var array<string,string>
     */
    protected const CONSTANT_ALIAS_MAP = [
        'OPERATOR_ARITHMETIC' => 'arithmetic operators',
        'OPERATOR_ASSIGNMENT' => 'assignment operators',
        'OPERATOR_BITWISE' => 'bitwise operators',
        'OPERATOR_COMPARISON' => 'comparison operators',
        'OPERATOR_LOGICAL' => 'logical operators',
        'OPERATOR_TERNARY' => 'ternary operators',
        'CAST' => 'casts',
        'KEYWORD' => 'keywords',
        'MODIFIER' => 'modifiers',
        'VISIBILITY' => 'visibility modifiers',
        'MAGIC_CONSTANT' => 'magic constants',
    ];

    public function testValues(): void
    {
        $index = static::getIndex();
        $properties = Reflect::getNames(static::getProperties());
        foreach ($properties as $property) {
            $value = $index->$property;
            if (is_array($value)) {
                ksort($value, \SORT_NUMERIC);
                $sorted[$property] = $value;
            }
        }
        $properties = $sorted ?? [];
        $unique = Arr::unique($properties);
        $notUnique = [];
        foreach ($unique as $value) {
            $same = array_uintersect(
                $properties,
                [$value],
                fn($a, $b) => $a <=> $b,
            );
            if (count($same) > 1) {
                $notUnique[] = implode(', ', array_keys($same));
            }
        }
        $this->assertEmpty($notUnique, sprintf(
            '%s properties do not have unique values: %s',
            get_class($index),
            implode('; ', $notUnique),
        ));
    }

    /**
     * @dataProvider propertyProvider
     */
    public function testIndexes(ReflectionProperty $property, TokenTypeIndex $index, string $name): void
    {
        $this->assertSameSize(TokenTypeIndex::TOKEN_INDEX, array_intersect_key($index->$name, TokenTypeIndex::TOKEN_INDEX), 'Index must cover every token type');
        $this->assertEmpty(array_diff_key($index->$name, TokenTypeIndex::TOKEN_INDEX), 'Index must only cover token types');
        $filtered = array_filter($index->$name);
        if (Regex::match('/^(?:Alt|AllowBlank|Suppress)/', $name)) {
            $this->assertNotEmpty($filtered, 'Index cannot be empty');
            return;
        }
        $this->assertGreaterThan(1, count($filtered), 'Index must match two or more token types');
    }

    /**
     * @dataProvider propertyProvider
     */
    public function testDocBlocks(ReflectionProperty $property, TokenTypeIndex $index, string $name): void
    {
        $expected = self::getTokenNames(self::getIndexTokens($index->$name));
        $message = sprintf('PHPDoc summary could be: %s', self::collapseTokenNames($expected));
        $expected = Arr::sort($expected);
        $comment = $property->getDocComment();
        $this->assertIsString($comment, $message);
        $phpDoc = new PHPDoc($comment);
        if ($phpDoc->hasTag('prettyphp-dynamic')) {
            return;
        }
        $this->assertNotNull($summary = $phpDoc->getSummary(), $message);
        $actual = Arr::sort(self::expandTokenNames($summary));
        $this->assertSame($expected, $actual, $message);
    }

    /**
     * @dataProvider addSpaceProvider
     *
     * @param int[] $array
     */
    public function testAddSpace(array $array): void
    {
        $this->assertSame([], self::getTokenNames($array));
    }

    /**
     * @return array<string,array<int[]>>
     */
    public static function addSpaceProvider(): array
    {
        $index = static::getIndex();
        $around = self::getIndexTokens($index->AddSpace);
        $before = self::getIndexTokens($index->AddSpaceBefore);
        $after = self::getIndexTokens($index->AddSpaceAfter);

        return [
            'Intersection of $AddSpaceBefore and $AddSpaceAfter' => [
                array_intersect($before, $after),
            ],
            'Intersection of $AddSpaceBefore and $AddSpaceAfter, not in $AddSpace' => [
                array_diff(
                    array_intersect($before, $after),
                    $around
                ),
            ],
            'Intersection of $AddSpace and $AddSpaceBefore' => [
                array_intersect($around, $before),
            ],
            'Intersection of $AddSpace and $AddSpaceAfter' => [
                array_intersect($around, $after),
            ],
        ];
    }

    /**
     * @dataProvider preserveNewlineProvider
     *
     * @param int[] $expected
     * @param int[] $array
     */
    public function testPreserveNewline(array $expected, array $array): void
    {
        $this->assertEquals(
            Arr::sort(self::getTokenNames($expected)),
            Arr::sort(self::getTokenNames($array)),
        );
    }

    /**
     * @return iterable<string,array{int[],int[]}>
     */
    public static function preserveNewlineProvider(): iterable
    {
        $idx = static::getIndex();
        $mixed = $idx->withMixedOperators();
        $first = $idx->withLeadingOperators();
        $last = $idx->withTrailingOperators();

        $mixedBefore = self::getIndexTokens($mixed->AllowNewlineBefore);
        $mixedAfter = self::getIndexTokens($mixed->AllowNewlineAfter);
        $firstBefore = self::getIndexTokens($first->AllowNewlineBefore);
        $firstAfter = self::getIndexTokens($first->AllowNewlineAfter);
        $lastBefore = self::getIndexTokens($last->AllowNewlineBefore);
        $lastAfter = self::getIndexTokens($last->AllowNewlineAfter);

        $alwaysFirstOrLast = array_intersect(
            $mixedBefore,
            $firstBefore,
            $lastBefore,
            $mixedAfter,
            $firstAfter,
            $lastAfter,
        );

        $alwaysFirst = array_diff(
            array_intersect($mixedBefore, $firstBefore, $lastBefore),
            $alwaysFirstOrLast,
        );

        $alwaysLast = array_diff(
            array_intersect($mixedAfter, $firstAfter, $lastAfter),
            $alwaysFirstOrLast,
        );

        $maybeFirst = array_diff(
            array_unique(array_merge($mixedBefore, $firstBefore, $lastBefore)),
            $alwaysFirst,
        );

        $maybeLast = array_diff(
            array_unique(array_merge($mixedAfter, $firstAfter, $lastAfter)),
            $alwaysLast,
        );

        yield from [
            '[mixed] Allowed at start or end of line' => [
                static::ALWAYS_ALLOWED_AT_START_OR_END,
                array_intersect($mixedBefore, $mixedAfter),
            ],
            '[leading] Allowed at start or end of line' => [
                static::ALWAYS_ALLOWED_AT_START_OR_END,
                array_intersect($firstBefore, $firstAfter),
            ],
            '[trailing] Allowed at start or end of line' => [
                static::ALWAYS_ALLOWED_AT_START_OR_END,
                array_intersect($lastBefore, $lastAfter),
            ],
            'Difference between [leading] $AllowNewlineBefore and [mixed] $AllowNewlineBefore' => [
                static::LEADING_OPERATORS,
                array_diff($firstBefore, $mixedBefore),
            ],
            'Difference between [mixed] $AllowNewlineAfter and [leading] $AllowNewlineAfter' => [
                static::LEADING_OPERATORS,
                array_diff($mixedAfter, $firstAfter),
            ],
            'Difference between [mixed] $AllowNewlineBefore and [trailing] $AllowNewlineBefore' => [
                static::TRAILING_OPERATORS,
                array_diff($mixedBefore, $lastBefore),
            ],
            'Difference between [trailing] $AllowNewlineAfter and [mixed] $AllowNewlineAfter' => [
                static::TRAILING_OPERATORS,
                array_diff($lastAfter, $mixedAfter),
            ],
            'Not in $Movable but may move to start or end of line' => [
                static::NOT_MOVABLE,
                array_diff(
                    array_unique(array_merge($maybeFirst, $maybeLast)),
                    self::getIndexTokens($mixed->Movable),
                ),
            ],
            'Always allowed at start of line' => [
                static::ALWAYS_ALLOWED_AT_START,
                $alwaysFirst,
            ],
            'Always allowed at end of line' => [
                static::ALWAYS_ALLOWED_AT_END,
                $alwaysLast,
            ],
            'Always allowed at start or end of line' => [
                static::ALWAYS_ALLOWED_AT_START_OR_END,
                $alwaysFirstOrLast,
            ],
            'Maybe allowed at start of line' => [
                static::MAYBE_ALLOWED_AT_START,
                $maybeFirst,
            ],
            'Maybe allowed at end of line' => [
                static::MAYBE_ALLOWED_AT_START,
                $maybeLast,
            ],
            '[mixed] Difference between $AllowBlankBefore and $AllowNewlineBefore' => [
                [],
                array_diff(self::getIndexTokens($mixed->AllowBlankBefore), $mixedBefore),
            ],
            '[mixed] Difference between $AllowBlankAfter and $AllowNewlineAfter' => [
                [],
                array_diff(self::getIndexTokens($mixed->AllowBlankAfter), $mixedAfter),
            ],
            '[leading] Difference between $AllowBlankBefore and $AllowNewlineBefore' => [
                [],
                array_diff(self::getIndexTokens($first->AllowBlankBefore), $firstBefore),
            ],
            '[leading] Difference between $AllowBlankAfter and $AllowNewlineAfter' => [
                [],
                array_diff(self::getIndexTokens($first->AllowBlankAfter), $firstAfter),
            ],
            '[trailing] Difference between $AllowBlankBefore and $AllowNewlineBefore' => [
                [],
                array_diff(self::getIndexTokens($last->AllowBlankBefore), $lastBefore),
            ],
            '[trailing] Difference between $AllowBlankAfter and $AllowNewlineAfter' => [
                [],
                array_diff(self::getIndexTokens($last->AllowBlankAfter), $lastAfter),
            ],
        ];

        if (static::class === self::class) {
            $parts = explode(
                "### After\n",
                Str::eolFromNative(File::getContents(self::getPackagePath() . '/docs/Newlines.md')),
            );
            unset($parts[0]);
            $doc = [];
            foreach ($parts as $part) {
                [$after, $before] = explode("### Before\n", $part);
                $doc[] = [
                    self::getDocTokens($after),
                    self::getDocTokens($before),
                ];
            }

            yield from [
                'Newlines > Mixed > After and [mixed] $AllowNewlineAfter' => [$doc[0][0], $mixedAfter],
                'Newlines > Mixed > Before and [mixed] $AllowNewlineBefore' => [$doc[0][1], $mixedBefore],
                'Newlines > Operators first > After and [leading] $AllowNewlineAfter' => [$doc[1][0], $firstAfter],
                'Newlines > Operators first > Before and [leading] $AllowNewlineBefore' => [$doc[1][1], $firstBefore],
                'Newlines > Operators last > After and [trailing] $AllowNewlineAfter' => [$doc[2][0], $lastAfter],
                'Newlines > Operators last > Before and [trailing] $AllowNewlineBefore' => [$doc[2][1], $lastBefore],
            ];
        }
    }

    /**
     * @return int[]
     */
    private static function getDocTokens(string $doc): array
    {
        $lines = Regex::grep('/^- /', explode("\n", $doc));
        foreach ($lines as $line) {
            if (!Regex::match(
                '/^- (?:`(?<token>T_[A-Z_]+)`|(?<operators>[a-zA-Z]+) operators(?: \(except `(?<exception>T_[A-Z_]+)`\))?)/',
                $line,
                $matches,
                \PREG_UNMATCHED_AS_NULL,
            )) {
                throw new LogicException('Invalid line: ' . $line);
            }
            if ($matches['token'] !== null) {
                /** @var int */
                $id = constant($matches['token']);
                $tokens[] = $id;
            } else {
                $operators = [
                    'Arithmetic' => TokenTypeIndex::OPERATOR_ARITHMETIC,
                    'Assignment' => TokenTypeIndex::OPERATOR_ASSIGNMENT,
                    'Bitwise' => TokenTypeIndex::OPERATOR_BITWISE,
                    'Comparison' => TokenTypeIndex::OPERATOR_COMPARISON,
                    'Comparison,T_COALESCE' => [\T_COALESCE => false] + TokenTypeIndex::OPERATOR_COMPARISON,
                    'Logical' => TokenTypeIndex::OPERATOR_LOGICAL,
                    'Logical,T_LOGICAL_NOT' => [\T_LOGICAL_NOT => false] + TokenTypeIndex::OPERATOR_LOGICAL,
                    'Ternary' => TokenTypeIndex::OPERATOR_TERNARY,
                ][Arr::implode(',', [$matches['operators'], $matches['exception']], '')] ?? null;
                if ($operators === null) {
                    throw new LogicException('Invalid operators: ' . $line);
                }
                $tokens = array_merge($tokens ?? [], array_keys(array_filter($operators)));
            }
        }
        return $tokens ?? [];
    }

    /**
     * @return Generator<string,array{ReflectionProperty,TokenTypeIndex,string}>
     */
    public static function propertyProvider(): Generator
    {
        $index = static::getIndex();
        foreach (static::getProperties() as $property) {
            $name = $property->getName();
            yield $name => [$property, $index, $name];
        }
    }

    /**
     * @return ReflectionProperty[]
     */
    protected static function getProperties(): array
    {
        return (new ReflectionClass(static::getIndex()))
                   ->getProperties(ReflectionProperty::IS_PUBLIC);
    }

    protected static function getIndex(): TokenTypeIndex
    {
        return new TokenTypeIndex();
    }

    /**
     * @param string[] $tokens
     */
    private static function collapseTokenNames(array $tokens): string
    {
        $_tokens = $tokens;
        foreach (self::getConstantTokenNames() as $alias => $names) {
            if (array_diff($names, $alias === 'keywords' ? $_tokens : $tokens)) {
                continue;
            }
            $collapsed[] = $alias;
            $tokens = array_diff($tokens, $names);
        }
        return Str::upperFirst(implode(', ', array_merge($collapsed ?? [], $tokens)));
    }

    /**
     * @return string[]
     */
    private static function expandTokenNames(string $string): array
    {
        $constants = self::getConstantTokenNames();
        $tokens = [];
        foreach (Str::splitDelimited(',', $string, false, null, 0) as $part) {
            if (Str::startsWith($part, 'T_')) {
                $tokens[] = $part;
                continue;
            }
            $alias = $part;
            $split = explode(' (except ', $part);
            if (count($split) === 2) {
                [$alias, $except] = $split;
                $except = substr($except, 0, -1);
                $except = explode(', ', $except);
            } else {
                $except = [];
            }
            $alias = Str::lower($alias);
            $expanded = $constants[$alias] ?? null;
            if ($expanded === null) {
                $tokens[] = "<invalid alias '{$alias}'>";
                continue;
            }
            if ($alias === 'keywords' && $tokens) {
                $expanded = array_diff($expanded, $tokens);
            }
            if ($except && ($diff = array_diff($except, $expanded))) {
                foreach ($diff as $exception) {
                    $tokens[] = "<invalid exception '{$exception}' for alias '{$alias}'>";
                }
            }
            foreach (array_diff($expanded, $except) as $part) {
                $tokens[] = $part;
            }
        }
        return $tokens;
    }

    /**
     * @return array<string,string[]>
     */
    private static function getConstantTokenNames(): array
    {
        $class = new ReflectionClass(static::getIndex());
        foreach (static::CONSTANT_ALIAS_MAP as $name => $alias) {
            $value = $class->getConstant($name);
            if (!is_array($value) || array_filter($value) !== $value) {
                throw new ShouldNotHappenException(sprintf(
                    'Invalid value or constant not found: %s::%s',
                    $class->getName(),
                    $name,
                ));
            }
            /** @var array<int,true> $value */
            $values[$alias] = self::getTokenNames(array_keys($value));
        }
        return $values ?? [];
    }

    /**
     * @param array<int,bool> $index
     * @return int[]
     */
    private static function getIndexTokens(array $index): array
    {
        foreach ($index as $type => $value) {
            if ($value) {
                $types[] = $type;
            }
        }
        return $types ?? [];
    }
}
