<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Catalog;

use Lkrms\PrettyPHP\Catalog\CustomToken;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Generator;
use ReflectionClass;

final class TokenTypeTest extends \Lkrms\PrettyPHP\Tests\TestCase
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
            $this->getTokenTypeNames(array_diff_key($array, array_unique($array)))
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
        $this->assertSame([], $this->getTokenTypeNames($array));
    }

    /**
     * @return array<string,array<int[]>>
     */
    public static function addSpaceProvider(): array
    {
        $index = new TokenTypeIndex();
        $around = self::indexToList($index->AddSpaceAround);
        $before = self::indexToList($index->AddSpaceBefore);
        $after = self::indexToList($index->AddSpaceAfter);

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
            $this->getTokenTypeNames(array_values($expected)),
            $this->getTokenTypeNames(array_values($array))
        );
    }

    /**
     * @return array<string,array<int[]>>
     */
    public static function preserveNewlineProvider(): array
    {
        $default = new TokenTypeIndex();
        $leading = $default->withLeadingOperators();
        $trailing = $default->withTrailingOperators();
        $defaultBefore = self::indexToList($default->PreserveNewlineBefore);
        $defaultAfter = self::indexToList($default->PreserveNewlineAfter);
        $leadingBefore = self::indexToList($leading->PreserveNewlineBefore);
        $leadingAfter = self::indexToList($leading->PreserveNewlineAfter);
        $trailingBefore = self::indexToList($trailing->PreserveNewlineBefore);
        $trailingAfter = self::indexToList($trailing->PreserveNewlineAfter);

        return [
            '[default] Intersection of TokenTypeIndex::$PreserveNewlineBefore and $PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_DOUBLE_ARROW,
                    \T_COLON,
                ],
                array_intersect($defaultBefore, $defaultAfter),
            ],
            '[leading] Intersection of TokenTypeIndex::$PreserveNewlineBefore and $PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_DOUBLE_ARROW,
                    \T_COLON,
                ],
                array_intersect($leadingBefore, $leadingAfter),
            ],
            '[trailing] Intersection of TokenTypeIndex::$PreserveNewlineBefore and $PreserveNewlineAfter' => [
                [
                    \T_ATTRIBUTE,
                    \T_ATTRIBUTE_COMMENT,
                    \T_DOUBLE_ARROW,
                    \T_COLON,
                ],
                array_intersect($trailingBefore, $trailingAfter),
            ],
            'Difference between [leading] TokenTypeIndex::$PreserveNewlineBefore and [default] $PreserveNewlineBefore' => [
                [
                    \T_BOOLEAN_AND,
                    \T_BOOLEAN_OR,
                    \T_LOGICAL_AND,
                    \T_LOGICAL_OR,
                    \T_LOGICAL_XOR,
                ],
                array_diff($leadingBefore, $defaultBefore),
            ],
            'Difference between [default] TokenTypeIndex::$PreserveNewlineAfter and [leading] $PreserveNewlineAfter' => [
                [
                    \T_LOGICAL_AND,
                    \T_LOGICAL_OR,
                    \T_LOGICAL_XOR,
                    \T_BOOLEAN_AND,
                    \T_BOOLEAN_OR,
                ],
                array_diff($defaultAfter, $leadingAfter),
            ],
            'Difference between [default] TokenTypeIndex::$PreserveNewlineBefore and [trailing] $PreserveNewlineBefore' => [
                [
                    \T_COALESCE,
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
                array_diff($defaultBefore, $trailingBefore),
            ],
            'Difference between [trailing] TokenTypeIndex::$PreserveNewlineAfter and [default] $PreserveNewlineAfter' => [
                [
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
                ],
                array_diff($trailingAfter, $defaultAfter),
            ],
        ];
    }

    /**
     * @param int[] $tokens
     * @return string[]
     */
    private function getTokenTypeNames(array $tokens): array
    {
        return array_map(
            function (int $id): string {
                $name = token_name($id);
                if (substr($name, 0, 2) !== 'T_') {
                    return CustomToken::toName($id);
                }
                return $name;
            },
            $tokens
        );
    }

    /**
     * @param array<int,bool> $index
     * @return int[]
     */
    private static function indexToList(array $index): array
    {
        foreach ($index as $type => $value) {
            if ($value) {
                $list[] = $type;
            }
        }
        return $list ?? [];
    }
}
