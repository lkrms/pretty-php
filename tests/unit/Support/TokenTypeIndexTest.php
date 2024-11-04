<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Support;

use Lkrms\PrettyPHP\Catalog\TokenGroup;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Tests\TestCase;
use Salient\PHPDoc\PHPDoc;
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
        $filtered = array_filter($index->$name);
        if (Regex::match('/^(?:Suppress|Preserve|AltSyntax)/', $name)) {
            $this->assertNotEmpty($filtered, 'Index cannot be empty');
            return;
        }
        $this->assertGreaterThan(1, count($filtered), 'Index must have two or more token types');
    }

    /**
     * @dataProvider propertyProvider
     */
    public function testDocBlocks(ReflectionProperty $property, TokenTypeIndex $index, string $name): void
    {
        $expected = Arr::sort(self::getTokenNames(self::getIndexTokens($index->$name)));
        $message = sprintf('PHPDoc summary could be: %s', implode(', ', $expected));
        $comment = $property->getDocComment();
        $this->assertIsString($comment, $message);
        $phpDoc = new PHPDoc($comment);
        if ($phpDoc->hasTag('internal')) {
            return;
        }
        $this->assertNotNull($summary = $phpDoc->getSummary(), $message);
        $actual = Arr::sort(explode(', ', $summary));
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
        $around = self::getIndexTokens($index->AddSpaceAround);
        $before = self::getIndexTokens($index->AddSpaceBefore);
        $after = self::getIndexTokens($index->AddSpaceAfter);

        return [
            'Intersection of $AddSpaceBefore and $AddSpaceAfter' => [
                array_intersect($before, $after),
            ],
            'Intersection of $AddSpaceBefore and $AddSpaceAfter, not in $AddSpaceAround' => [
                array_diff(
                    array_intersect($before, $after),
                    $around
                ),
            ],
            'Intersection of $AddSpaceAround and $AddSpaceBefore' => [
                array_intersect($around, $before),
            ],
            'Intersection of $AddSpaceAround and $AddSpaceAfter' => [
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
    public function testPreserveNewline(array $expected, array $array, bool $sort = false): void
    {
        $this->assertEquals(
            self::getTokenNames($sort ? Arr::sort($expected) : $expected),
            self::getTokenNames($sort ? Arr::sort($array) : $array)
        );
    }

    /**
     * @return array<string,array{int[],int[],2?:bool}>
     */
    public static function preserveNewlineProvider(): array
    {
        $idx = static::getIndex();
        $mixed = $idx->withMixedOperators();
        $first = $idx->withLeadingOperators();
        $last = $idx->withTrailingOperators();

        $mixedBefore = self::getIndexTokens($mixed->PreserveNewlineBefore);
        $mixedAfter = self::getIndexTokens($mixed->PreserveNewlineAfter);
        $firstBefore = self::getIndexTokens($first->PreserveNewlineBefore);
        $firstAfter = self::getIndexTokens($first->PreserveNewlineAfter);
        $lastBefore = self::getIndexTokens($last->PreserveNewlineBefore);
        $lastAfter = self::getIndexTokens($last->PreserveNewlineAfter);

        $maybeFirst = array_diff(
            array_unique(array_merge($mixedBefore, $firstBefore, $lastBefore)),
            array_intersect($mixedBefore, $firstBefore, $lastBefore),
        );

        $maybeLast = array_diff(
            array_unique(array_merge($mixedAfter, $firstAfter, $lastAfter)),
            array_intersect($mixedAfter, $firstAfter, $lastAfter),
        );

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

        return [
            '[mixed] Intersection of $PreserveNewlineBefore and $PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_DOUBLE_ARROW,
                    \T_COLON,
                ],
                array_intersect($mixedBefore, $mixedAfter),
            ],
            '[leading] Intersection of $PreserveNewlineBefore and $PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_DOUBLE_ARROW,
                    \T_COLON,
                ],
                array_intersect($firstBefore, $firstAfter),
            ],
            '[trailing] Intersection of $PreserveNewlineBefore and $PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_DOUBLE_ARROW,
                    \T_COLON,
                ],
                array_intersect($lastBefore, $lastAfter),
            ],
            'Difference between [leading] $PreserveNewlineBefore and [mixed] $PreserveNewlineBefore' => [
                [
                    \T_SMALLER,
                    \T_GREATER,
                    \T_IS_EQUAL,
                    \T_IS_IDENTICAL,
                    \T_IS_NOT_EQUAL,
                    \T_IS_NOT_IDENTICAL,
                    \T_IS_SMALLER_OR_EQUAL,
                    \T_IS_GREATER_OR_EQUAL,
                    \T_SPACESHIP,
                    \T_LOGICAL_AND,
                    \T_LOGICAL_OR,
                    \T_LOGICAL_XOR,
                    \T_BOOLEAN_AND,
                    \T_BOOLEAN_OR,
                ],
                array_diff($firstBefore, $mixedBefore),
            ],
            'Difference between [mixed] $PreserveNewlineAfter and [leading] $PreserveNewlineAfter' => [
                [
                    \T_SMALLER,
                    \T_GREATER,
                    \T_IS_EQUAL,
                    \T_IS_IDENTICAL,
                    \T_IS_NOT_EQUAL,
                    \T_IS_NOT_IDENTICAL,
                    \T_IS_SMALLER_OR_EQUAL,
                    \T_IS_GREATER_OR_EQUAL,
                    \T_SPACESHIP,
                    \T_LOGICAL_AND,
                    \T_LOGICAL_OR,
                    \T_LOGICAL_XOR,
                    \T_BOOLEAN_AND,
                    \T_BOOLEAN_OR,
                ],
                array_diff($mixedAfter, $firstAfter),
            ],
            'Difference between [mixed] $PreserveNewlineBefore and [trailing] $PreserveNewlineBefore' => [
                [
                    \T_COALESCE,
                    \T_CONCAT,
                    \T_PLUS,
                    \T_MINUS,
                    \T_MUL,
                    \T_DIV,
                    \T_MOD,
                    \T_POW,
                    \T_OR,
                    \T_XOR,
                    \T_NOT,
                    \T_SL,
                    \T_SR,
                    \T_AND,
                    \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                    \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
                ],
                array_diff($mixedBefore, $lastBefore),
            ],
            'Difference between [trailing] $PreserveNewlineAfter and [mixed] $PreserveNewlineAfter' => [
                [
                    \T_COALESCE,
                    \T_CONCAT,
                    \T_PLUS,
                    \T_MINUS,
                    \T_MUL,
                    \T_DIV,
                    \T_MOD,
                    \T_POW,
                    \T_OR,
                    \T_XOR,
                    \T_NOT,
                    \T_SL,
                    \T_SR,
                    \T_AND,
                    \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                    \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
                ],
                array_diff($lastAfter, $mixedAfter),
            ],
            'Difference between $Movable and $maybeFirst (calculated)' => [
                [
                    \T_EQUAL,
                    \T_COALESCE_EQUAL,
                    \T_PLUS_EQUAL,
                    \T_MINUS_EQUAL,
                    \T_MUL_EQUAL,
                    \T_DIV_EQUAL,
                    \T_MOD_EQUAL,
                    \T_POW_EQUAL,
                    \T_AND_EQUAL,
                    \T_OR_EQUAL,
                    \T_XOR_EQUAL,
                    \T_SL_EQUAL,
                    \T_SR_EQUAL,
                    \T_CONCAT_EQUAL,
                ],
                array_diff(self::getIndexTokens($mixed->Movable), $maybeFirst),
            ],
            'Difference between $Movable and $maybeLast (calculated)' => [
                [
                    \T_EQUAL,
                    \T_COALESCE_EQUAL,
                    \T_PLUS_EQUAL,
                    \T_MINUS_EQUAL,
                    \T_MUL_EQUAL,
                    \T_DIV_EQUAL,
                    \T_MOD_EQUAL,
                    \T_POW_EQUAL,
                    \T_AND_EQUAL,
                    \T_OR_EQUAL,
                    \T_XOR_EQUAL,
                    \T_SL_EQUAL,
                    \T_SR_EQUAL,
                    \T_CONCAT_EQUAL,
                ],
                array_diff(self::getIndexTokens($mixed->Movable), $maybeLast),
            ],
            'Intersection of *::$PreserveNewlineBefore' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_CLOSE_BRACKET,
                    \T_CLOSE_PARENTHESIS,
                    \T_CLOSE_TAG,
                    \T_DOUBLE_ARROW,
                    \T_LOGICAL_NOT,
                    \T_NULLSAFE_OBJECT_OPERATOR,
                    \T_OBJECT_OPERATOR,
                    \T_QUESTION,
                    \T_COLON,
                ],
                array_intersect($mixedBefore, $firstBefore, $lastBefore),
            ],
            'Intersection of *::$PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_CLOSE_BRACE,
                    \T_COLON,
                    \T_COMMA,
                    \T_COMMENT,
                    \T_DOC_COMMENT,
                    \T_DOUBLE_ARROW,
                    \T_EXTENDS,
                    \T_IMPLEMENTS,
                    \T_OPEN_BRACE,
                    \T_OPEN_BRACKET,
                    \T_OPEN_PARENTHESIS,
                    \T_OPEN_TAG,
                    \T_OPEN_TAG_WITH_ECHO,
                    \T_SEMICOLON,
                    \T_EQUAL,
                    \T_COALESCE_EQUAL,
                    \T_PLUS_EQUAL,
                    \T_MINUS_EQUAL,
                    \T_MUL_EQUAL,
                    \T_DIV_EQUAL,
                    \T_MOD_EQUAL,
                    \T_POW_EQUAL,
                    \T_AND_EQUAL,
                    \T_OR_EQUAL,
                    \T_XOR_EQUAL,
                    \T_SL_EQUAL,
                    \T_SR_EQUAL,
                    \T_CONCAT_EQUAL,
                ],
                array_intersect($mixedAfter, $firstAfter, $lastAfter),
            ],
            'Intersection of *::$PreserveNewlineBefore and *::$PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_DOUBLE_ARROW,
                    \T_COLON,
                ],
                array_intersect($mixedBefore, $firstBefore, $lastBefore, $mixedAfter, $firstAfter, $lastAfter),
            ],
            '[mixed] Difference between $PreserveBlankBefore and $PreserveNewlineBefore' => [
                [],
                array_diff(self::getIndexTokens($mixed->PreserveBlankBefore), $mixedBefore),
            ],
            '[mixed] Difference between $PreserveBlankAfter and $PreserveNewlineAfter' => [
                [],
                array_diff(self::getIndexTokens($mixed->PreserveBlankAfter), $mixedAfter),
            ],
            '[leading] Difference between $PreserveBlankBefore and $PreserveNewlineBefore' => [
                [],
                array_diff(self::getIndexTokens($first->PreserveBlankBefore), $firstBefore),
            ],
            '[leading] Difference between $PreserveBlankAfter and $PreserveNewlineAfter' => [
                [],
                array_diff(self::getIndexTokens($first->PreserveBlankAfter), $firstAfter),
            ],
            '[trailing] Difference between $PreserveBlankBefore and $PreserveNewlineBefore' => [
                [],
                array_diff(self::getIndexTokens($last->PreserveBlankBefore), $lastBefore),
            ],
            '[trailing] Difference between $PreserveBlankAfter and $PreserveNewlineAfter' => [
                [],
                array_diff(self::getIndexTokens($last->PreserveBlankAfter), $lastAfter),
            ],
            'Newlines > Mixed > After and [mixed] $PreserveNewlineAfter' => [$doc[0][0], $mixedAfter, true],
            'Newlines > Mixed > Before and [mixed] $PreserveNewlineBefore' => [$doc[0][1], $mixedBefore, true],
            'Newlines > Operators first > After and [leading] $PreserveNewlineAfter' => [$doc[1][0], $firstAfter, true],
            'Newlines > Operators first > Before and [leading] $PreserveNewlineBefore' => [$doc[1][1], $firstBefore, true],
            'Newlines > Operators last > After and [trailing] $PreserveNewlineAfter' => [$doc[2][0], $lastAfter, true],
            'Newlines > Operators last > Before and [trailing] $PreserveNewlineBefore' => [$doc[2][1], $lastBefore, true],
        ];
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
                    'Arithmetic' => TokenGroup::OPERATOR_ARITHMETIC,
                    'Assignment' => TokenGroup::OPERATOR_ASSIGNMENT,
                    'Bitwise' => TokenGroup::OPERATOR_BITWISE,
                    'Comparison' => TokenGroup::OPERATOR_COMPARISON,
                    'Comparison,T_COALESCE' => TokenGroup::OPERATOR_COMPARISON_EXCEPT_COALESCE,
                    'Logical' => TokenGroup::OPERATOR_LOGICAL,
                    'Logical,T_LOGICAL_NOT' => TokenGroup::OPERATOR_LOGICAL_EXCEPT_NOT,
                    'Ternary' => TokenGroup::OPERATOR_TERNARY,
                ][Arr::implode(',', [$matches['operators'], $matches['exception']], '')] ?? null;
                if ($operators === null) {
                    throw new LogicException('Invalid operators: ' . $line);
                }
                $tokens = array_merge($tokens ?? [], $operators);
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
