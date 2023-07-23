<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Generator;
use Lkrms\Pretty\Php\Catalog\CustomToken;
use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Support\TokenTypeIndex;
use ReflectionClass;

final class TokenTypeTest extends \Lkrms\Pretty\Tests\Php\TestCase
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
