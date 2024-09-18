<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Catalog;

use Lkrms\PrettyPHP\Catalog\TokenGroup;
use Lkrms\PrettyPHP\Tests\TestCase;
use Salient\Utility\Arr;
use Generator;
use ReflectionClass;

final class TokenGroupTest extends TestCase
{
    public function testValues(): void
    {
        $constants = (new ReflectionClass(TokenGroup::class))->getConstants();
        foreach ($constants as $name => $value) {
            if (is_array($value)) {
                sort($value);
                $sorted[$name] = $value;
            }
        }
        $constants = $sorted ?? [];
        $unique = Arr::unique($constants);
        $notUnique = [];
        foreach ($unique as $value) {
            $same = array_uintersect(
                $constants,
                [$value],
                fn($a, $b) => $a <=> $b,
            );
            if (count($same) > 1) {
                $notUnique[] = implode(', ', array_keys($same));
            }
        }
        $this->assertEmpty($notUnique, sprintf(
            '%s constants do not have unique values: %s',
            TokenGroup::class,
            implode('; ', $notUnique),
        ));
    }

    /**
     * @dataProvider uniquenessProvider
     *
     * @param int[] $array
     */
    public function testUniqueness(array $array): void
    {
        $this->assertSame(
            [],
            self::getTokenNames(array_diff_key($array, array_unique($array)))
        );
    }

    /**
     * @return Generator<string,array<int[]>>
     */
    public static function uniquenessProvider(): Generator
    {
        $constants = (new ReflectionClass(TokenGroup::class))->getConstants();
        foreach ($constants as $name => $value) {
            if (is_array($value)) {
                yield $name => [$value];
            }
        }
    }
}
