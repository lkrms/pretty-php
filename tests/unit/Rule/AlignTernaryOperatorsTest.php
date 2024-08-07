<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignTernaryOperators;
use Lkrms\PrettyPHP\Tests\TestCase;

final class AlignTernaryOperatorsTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [AlignTernaryOperators::class]);
    }

    /**
     * @return array<array{string,string}>
     */
    public static function outputProvider(): array
    {
        return [
            [
                <<<'PHP'
<?php
$alpha = $bravo
             ?? $charlie
             ?: $delta
             ?? $echo
             ?: $foxtrot;
$alpha =
    $bravo
        ?? $charlie
        ?: $delta
        ?? $echo
        ?: $foxtrot;

PHP,
                <<<'PHP'
<?php
$alpha = $bravo
?? $charlie
?: $delta
?? $echo
?: $foxtrot;
$alpha =
$bravo
?? $charlie
?: $delta
?? $echo
?: $foxtrot;
PHP,
            ],
        ];
    }
}
