<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Filter\MoveComments;
use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;
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
            'binary operators #3' => [
                <<<'PHP'
<?php
$foo && $bar
    ? fn() =>
        !$foo($long, $list, $of, $arguments) &&
        $bar($long, $list, $of, $arguments)
    : ($foo
        ? fn() =>
            !$foo($long, $list, $of, $arguments)
        : fn() =>
            $bar($long, $list, $of, $arguments));

PHP,
                <<<'PHP'
<?php
$foo && $bar
? fn() =>
!$foo($long, $list, $of, $arguments) &&
$bar($long, $list, $of, $arguments)
: ($foo
? fn() =>
!$foo($long, $list, $of, $arguments)
: fn() =>
$bar($long, $list, $of, $arguments));
PHP,
                $formatter,
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
            'magic comma #3 (with PreserveOneLineStatements)' => [
                <<<'PHP'
<?php
$foo = [
    0,
    1,
    2,
];
bar(
    $baz,
    $qux,
);

PHP,
                <<<'PHP'
<?php
$foo = [0, 1, 2,];
bar($baz, $qux,);
PHP,
                $formatterB
                    ->enable([PreserveOneLineStatements::class])
                    ->build(),
            ],
            'idempotent brackets' => [
                <<<'PHP'
<?php
if (
    (
        $foo &&
        $bar
    ) || (
        $baz &&
        $qux
    )
) {
    //
}

PHP,
                <<<'PHP'
<?php
if (
    (
        $foo
        && $bar)
    || (
        $baz
        && $qux
    )
) {
    //
}
PHP,
                $formatter,
            ],
        ];
    }
}
