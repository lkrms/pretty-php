<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\StrictLists;
use Lkrms\PrettyPHP\Tests\TestCase;

final class StrictListsTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [StrictLists::class]);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function outputProvider(): array
    {
        return [
            'multi-line array' => [
                <<<'PHP'
<?php
$a = [
    $b,
    $c,
    $d
];

PHP,
                <<<'PHP'
<?php
$a = [$b,
$c, $d];
PHP,
            ],
            'multi-line array with opening newline' => [
                <<<'PHP'
<?php
$a = [$b, $c, $d];

PHP,
                <<<'PHP'
<?php
$a = [
$b, $c, $d];
PHP,
            ],
            'multi-line array with multi-line element' => [
                <<<'PHP'
<?php
$a = [($b ||
    $c), $d, $e, $f];

PHP,
                <<<'PHP'
<?php
$a = [($b ||
$c), $d,
$e, $f];
PHP,
            ],
            'one-line array' => [
                <<<'PHP'
<?php
$a = [$b, $c];

PHP,
                <<<'PHP'
<?php
$a = [$b, $c];
PHP,
            ],
            'one-line array with multi-line element' => [
                <<<'PHP'
<?php
$a = [($b ||
    $c), $d];

PHP,
                <<<'PHP'
<?php
$a = [($b ||
$c), $d];
PHP,
            ],
            'argument variations' => [
                <<<'PHP'
<?php
F($a, $b, $c, $d);
F($a, $b, $c, $d);
F(
    $a,
    $b,
    $c,
    $d
);
F($a, $b, $c, $d);
F(
    $a,
    $b,
    $c,
    $d
);
F($a, $b, $c, $d);

PHP,
                <<<'PHP'
<?php
F($a, $b, $c, $d);
F(
    $a, $b, $c, $d
);
F($a,
    $b, $c, $d);
F($a, $b,
    $c, $d);
F(
    $a,
    $b, $c, $d
);
F(
    $a, $b,
    $c,
    $d
);
PHP,
            ]
        ];
    }
}
