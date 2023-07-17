<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Generator;
use Lkrms\Pretty\Php\Catalog\CustomToken;
use Lkrms\Pretty\Php\Catalog\TokenType;
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
        return [
            'Intersection of TokenType::ADD_SPACE_BEFORE and _AFTER' => [
                array_intersect(TokenType::ADD_SPACE_BEFORE, TokenType::ADD_SPACE_AFTER),
            ],
            'Intersection of TokenType::ADD_SPACE_BEFORE and _AFTER, not in _AROUND' => [
                array_diff(
                    array_intersect(TokenType::ADD_SPACE_BEFORE, TokenType::ADD_SPACE_AFTER),
                    TokenType::ADD_SPACE_AROUND
                ),
            ],
            'Intersection of TokenType::ADD_SPACE_AROUND and _BEFORE' => [
                array_intersect(TokenType::ADD_SPACE_AROUND, TokenType::ADD_SPACE_BEFORE),
            ],
            'Intersection of TokenType::ADD_SPACE_AROUND and _AFTER' => [
                array_intersect(TokenType::ADD_SPACE_AROUND, TokenType::ADD_SPACE_AFTER),
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
}
