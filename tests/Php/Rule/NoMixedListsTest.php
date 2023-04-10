<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

use Lkrms\Pretty\Php\Rule\NoMixedLists;
use Lkrms\Pretty\Tests\Php\TestCase;

final class NoMixedListsTest extends TestCase
{
    /**
     * @dataProvider processListProvider
     */
    public function testProcessList(string $code, string $expected)
    {
        $this->assertFormatterOutputIs(
            $code,
            $expected,
            [NoMixedLists::class]
        );
    }

    public static function processListProvider(): array
    {
        return [
            'multi-line array' => [
                <<<'PHP'
<?php

$a = [$b,
$c, $d];
PHP,
                <<<'PHP'
<?php

$a = [
    $b,
    $c,
    $d
];

PHP,
            ],
            'multi-line array with opening newline' => [
                <<<'PHP'
<?php

$a = [
$b, $c, $d];
PHP,
                <<<'PHP'
<?php

$a = [
    $b, $c, $d
];

PHP,
            ],
            'multi-line array with multi-line element' => [
                <<<'PHP'
<?php

$a = [($b ||
$c), $d,
$e, $f];
PHP,
                <<<'PHP'
<?php

$a = [($b ||
    $c), $d, $e, $f];

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
                <<<'PHP'
<?php

F($a, $b, $c, $d);
F(
    $a, $b, $c, $d
);
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
            ]
        ];
    }
}
