<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Filter\MoveComments;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;
use Lkrms\PrettyPHP\TokenIndex;

final class VerticalSpacingTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     *
     * @param Formatter|FormatterB $formatter
     */
    public function testOutput(string $expected, string $code, $formatter): void
    {
        $this->assertFormatterOutputIs($expected, $code, $formatter);
    }

    /**
     * @return array<array{string,string,Formatter|FormatterB}>
     */
    public static function outputProvider(): array
    {
        $formatterB = Formatter::build();
        $formatter = $formatterB->build();

        return [
            'chain #1' => [
                <<<'PHP'
<?php
$foxtrot
    ->foo(
        fn() =>
            bar()
    )
    ->baz()
    ->quux();

PHP,
                <<<'PHP'
<?php
$foxtrot->foo(
fn() =>
bar()
)
->baz()
->quux();
PHP,
                $formatter,
            ],
            'chain #2' => [
                <<<'PHP'
<?php
$foxtrot
    ->foo(fn() =>
        bar())
    ->baz()
    ->quux();

PHP,
                <<<'PHP'
<?php
$foxtrot->foo(fn() =>
bar()
)
->baz()
->quux();
PHP,
                $formatter,
            ],
            'binary operators #1' => [
                <<<'PHP'
<?php
(
    $a ||
    (
        (
            $b
        ) && !(
            $c
        ) &&
        $d & $e &&
        ($f & $g)
    ) || (
        (
            $h
        ) && !(
            $i
        ) &&
        $j & $k &&
        ($l & $m)
    ) ||
    $n ||
    $o & $p ||
    ($q & $r)
);

PHP,
                <<<'PHP'
<?php
(
    $a
    ||
    (
        (
            $b
        )
        &&
        !(
            $c
        ) && $d & $e && ($f & $g)
    )
    ||
    (
        (
            $h
        )
        &&
        !(
            $i
        ) && $j & $k && ($l & $m)
    )
    ||
    $n || $o & $p || ($q & $r)
);
PHP,
                $formatter,
            ],
            'binary operators #2' => [
                <<<'PHP'
<?php
(
    $a
    || (
        (
            $b
        ) && !(
            $c
        )
        && $d & $e
        && ($f & $g)
    ) || (
        (
            $h
        ) && !(
            $i
        )
        && $j & $k
        && ($l & $m)
    )
    || $n
    || $o & $p
    || ($q & $r)
);

PHP,
                <<<'PHP'
<?php
(
    $a
    ||
    (
        (
            $b
        )
        &&
        !(
            $c
        ) && $d & $e && ($f & $g)
    )
    ||
    (
        (
            $h
        )
        &&
        !(
            $i
        ) && $j & $k && ($l & $m)
    )
    ||
    $n || $o & $p || ($q & $r)
);
PHP,
                $formatterB->tokenIndex(new TokenIndex(true)),
            ],
            'magic comma #1' => [
                <<<'PHP'
<?php
function getArray()
{
    return [
        'foo',
        'bar',
        'baz',
    ];
}

PHP,
                <<<'PHP'
<?php
function getArray()
{
    return ['foo', 'bar', 'baz',];
}
PHP,
                $formatter,
            ],
            'magic comma #2' => [
                <<<'PHP'
<?php
[
    ,,
    $foo,
] = $bar;
$foo = [  // comment
    $bar,
];
$foo = [
    ,,  // comment
    $bar,
];

PHP,
                <<<'PHP'
<?php
[,, $foo,] = $bar;
$foo = [  // comment
$bar,];
$foo = [  // comment
,, $bar,];
PHP,
                $formatter,
            ],
            'magic comma #2 with MoveComments disabled' => [
                <<<'PHP'
<?php
[
    ,,
    $foo,
] = $bar;
$foo = [  // comment
    $bar,
];
$foo = [  // comment
    ,,
    $bar,
];

PHP,
                <<<'PHP'
<?php
[,, $foo,] = $bar;
$foo = [  // comment
$bar,];
$foo = [  // comment
,, $bar,];
PHP,
                $formatterB
                    ->disable([MoveComments::class])
                    ->build(),
            ],
        ];
    }
}
