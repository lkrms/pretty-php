<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Support;

use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Tests\TestCase;
use Salient\Utility\Arr;
use Salient\Utility\Reflect;
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
    public function testPreserveNewline(array $expected, array $array): void
    {
        $this->assertEquals(
            self::getTokenNames(array_values($expected)),
            self::getTokenNames(array_values($array))
        );
    }

    /**
     * @return array<string,array<int[]>>
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
                    \T_COALESCE_EQUAL,
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
                    \T_COALESCE_EQUAL,
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
                [],
                array_diff(self::getIndexTokens($mixed->Movable), $maybeFirst),
            ],
            'Difference between $Movable and $maybeLast (calculated)' => [
                [],
                array_diff(self::getIndexTokens($mixed->Movable), $maybeLast),
            ],
            'Intersection of *::$PreserveNewlineBefore' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_CLOSE_BRACKET,
                    \T_CLOSE_PARENTHESIS,
                    \T_DOUBLE_ARROW,
                    \T_LOGICAL_NOT,
                    \T_NULLSAFE_OBJECT_OPERATOR,
                    \T_OBJECT_OPERATOR,
                    \T_CLOSE_TAG,
                    \T_QUESTION,
                    \T_COLON,
                ],
                array_intersect($mixedBefore, $firstBefore, $lastBefore),
            ],
            'Intersection of *::$PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_COLON,
                    \T_DOUBLE_ARROW,
                    \T_EXTENDS,
                    \T_IMPLEMENTS,
                    \T_OPEN_BRACE,
                    \T_OPEN_BRACKET,
                    \T_OPEN_PARENTHESIS,
                    \T_RETURN,
                    \T_THROW,
                    \T_YIELD,
                    \T_YIELD_FROM,
                    \T_CLOSE_BRACE,
                    \T_COMMA,
                    \T_COMMENT,
                    \T_DOC_COMMENT,
                    \T_OPEN_TAG,
                    \T_OPEN_TAG_WITH_ECHO,
                    \T_SEMICOLON,
                    \T_EQUAL,
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
        ];
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
