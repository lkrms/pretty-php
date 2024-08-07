<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Catalog;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Tests\TestCase;
use Generator;
use ReflectionClass;

final class TokenTypeTest extends TestCase
{
    /**
     * @dataProvider uniquenessProvider
     *
     * @param int[] $array
     */
    public function testUniqueness(array $array): void
    {
        $this->assertSame(
            [],
            TokenType::getNames(...array_diff_key($array, array_unique($array)))
        );
    }

    /**
     * @return Generator<string,array<int[]>>
     */
    public static function uniquenessProvider(): Generator
    {
        foreach ((new ReflectionClass(TokenType::class))->getConstants() as $name => $value) {
            if (is_array($value)) {
                yield $name => [$value];
            }
        }
    }

    /**
     * @dataProvider addSpaceProvider
     *
     * @param int[] $array
     */
    public function testAddSpace(array $array): void
    {
        $this->assertSame([], TokenType::getNames(...$array));
    }

    /**
     * @return array<string,array<int[]>>
     */
    public static function addSpaceProvider(): array
    {
        $index = new TokenTypeIndex();
        $around = TokenType::reduceIndex($index->AddSpaceAround);
        $before = TokenType::reduceIndex($index->AddSpaceBefore);
        $after = TokenType::reduceIndex($index->AddSpaceAfter);

        return [
            'Intersection of TokenTypeIndex::$AddSpaceBefore and $AddSpaceAfter' => [
                array_intersect($before, $after),
            ],
            'Intersection of TokenTypeIndex::$AddSpaceBefore and $AddSpaceAfter, not in $AddSpaceAround' => [
                array_diff(
                    array_intersect($before, $after),
                    $around
                ),
            ],
            'Intersection of TokenTypeIndex::$AddSpaceAround and $AddSpaceBefore' => [
                array_intersect($around, $before),
            ],
            'Intersection of TokenTypeIndex::$AddSpaceAround and $AddSpaceAfter' => [
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
            TokenType::getNames(...array_values($expected)),
            TokenType::getNames(...array_values($array))
        );
    }

    /**
     * @return array<string,array<int[]>>
     */
    public static function preserveNewlineProvider(): array
    {
        $idx = new TokenTypeIndex();
        $mixed = $idx->withMixedOperators();
        $first = $idx->withLeadingOperators();
        $last = $idx->withTrailingOperators();

        $mixedBefore = TokenType::reduceIndex($mixed->PreserveNewlineBefore);
        $mixedAfter = TokenType::reduceIndex($mixed->PreserveNewlineAfter);
        $firstBefore = TokenType::reduceIndex($first->PreserveNewlineBefore);
        $firstAfter = TokenType::reduceIndex($first->PreserveNewlineAfter);
        $lastBefore = TokenType::reduceIndex($last->PreserveNewlineBefore);
        $lastAfter = TokenType::reduceIndex($last->PreserveNewlineAfter);

        $maybeFirst = array_diff(
            array_unique(array_merge($mixedBefore, $firstBefore, $lastBefore)),
            array_intersect($mixedBefore, $firstBefore, $lastBefore),
        );

        $maybeLast = array_diff(
            array_unique(array_merge($mixedAfter, $firstAfter, $lastAfter)),
            array_intersect($mixedAfter, $firstAfter, $lastAfter),
        );

        return [
            '[mixed] Intersection of TokenTypeIndex::$PreserveNewlineBefore and $PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_DOUBLE_ARROW,
                    \T_COLON,
                ],
                array_intersect($mixedBefore, $mixedAfter),
            ],
            '[leading] Intersection of TokenTypeIndex::$PreserveNewlineBefore and $PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_DOUBLE_ARROW,
                    \T_COLON,
                ],
                array_intersect($firstBefore, $firstAfter),
            ],
            '[trailing] Intersection of TokenTypeIndex::$PreserveNewlineBefore and $PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_DOUBLE_ARROW,
                    \T_COLON,
                ],
                array_intersect($lastBefore, $lastAfter),
            ],
            'Difference between [leading] TokenTypeIndex::$PreserveNewlineBefore and [mixed] $PreserveNewlineBefore' => [
                [
                    \T_AND_EQUAL,
                    \T_BOOLEAN_AND,
                    \T_BOOLEAN_OR,
                    \T_CONCAT_EQUAL,
                    \T_DIV_EQUAL,
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
                    \T_MINUS_EQUAL,
                    \T_MOD_EQUAL,
                    \T_MUL_EQUAL,
                    \T_OR_EQUAL,
                    \T_PLUS_EQUAL,
                    \T_POW_EQUAL,
                    \T_SL_EQUAL,
                    \T_SMALLER,
                    \T_SPACESHIP,
                    \T_SR_EQUAL,
                    \T_XOR_EQUAL,
                ],
                array_diff($firstBefore, $mixedBefore),
            ],
            'Difference between [mixed] TokenTypeIndex::$PreserveNewlineAfter and [leading] $PreserveNewlineAfter' => [
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
            'Difference between [mixed] TokenTypeIndex::$PreserveNewlineBefore and [trailing] $PreserveNewlineBefore' => [
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
                    \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                    \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
                    \T_AND,
                    \T_OR,
                    \T_XOR,
                    \T_NOT,
                    \T_SL,
                    \T_SR,
                ],
                array_diff($mixedBefore, $lastBefore),
            ],
            'Difference between [trailing] TokenTypeIndex::$PreserveNewlineAfter and [mixed] $PreserveNewlineAfter' => [
                [
                    \T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG,
                    \T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG,
                    \T_AND,
                    \T_COALESCE,
                    \T_COALESCE_EQUAL,
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
                ],
                array_diff($lastAfter, $mixedAfter),
            ],
            'Difference between TokenTypeIndex::$Movable and $maybeFirst (calculated)' => [
                [],
                array_diff(TokenType::reduceIndex($mixed->Movable), $maybeFirst),
            ],
            'Difference between TokenTypeIndex::$Movable and $maybeLast (calculated)' => [
                [],
                array_diff(TokenType::reduceIndex($mixed->Movable), $maybeLast),
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
}
