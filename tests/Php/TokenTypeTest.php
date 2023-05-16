<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Lkrms\Pretty\Php\TokenType;
use ReflectionClass;

final class TokenTypeTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider uniquenessProvider
     *
     * @param array<int|string> $array
     */
    public function testUniqueness(array $array)
    {
        $this->assertSame(
            [],
            $this->getTokenTypeNames(array_diff_key($array, array_unique($array)))
        );
    }

    public static function uniquenessProvider()
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
     * @param array<int|string> $array
     */
    public function testAddSpace(array $array)
    {
        $this->assertSame([], $this->getTokenTypeNames($array));
    }

    public static function addSpaceProvider()
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
     * @param array<int|string> $tokens
     * @return string[]
     */
    private function getTokenTypeNames(array $tokens): array
    {
        return array_map(
            function ($id) {
                if (!is_int($id)) {
                    return $id;
                }
                $name = TokenType::NAME_MAP[$id] ?? token_name($id);
                if ($name === 'UNKNOWN') {
                    return chr($id);
                }

                return $name;
            },
            $tokens
        );
    }
}
