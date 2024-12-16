<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignChains;
use Lkrms\PrettyPHP\Rule\AlignTernaryOperators;
use Lkrms\PrettyPHP\Tests\TestCase;

final class AlignTernaryOperatorsTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [AlignTernaryOperators::class, AlignChains::class]);
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
$abc = $def->ghi()
           ->klm()
               ?: $abc;

PHP,
                <<<'PHP'
<?php
$abc = $def->ghi()
->klm()
?: $abc;
PHP,
            ],
            [
                <<<'PHP'
<?php
do
    $foo = $bar
               ? $baz
                     ? $qux
                     : $quux
               : $quuux;
while (false);

PHP,
                <<<'PHP'
<?php
do
$foo = $bar
? $baz
? $qux
: $quux
: $quuux;
while (false);
PHP,
            ],
            [
                <<<'PHP'
<?php
do
    $foo = $bar
               ? $baz
               : $qux
                     ? $quux
                     : $quuux;
while (false);

PHP,
                <<<'PHP'
<?php
do
$foo = $bar
? $baz
: $qux
? $quux
: $quuux;
while (false);
PHP,
            ],
            [
                <<<'PHP'
<?php
if (foo())
    $foo = $bar
               ? $baz
                     ? $qux
                     : $quux
               : $quuux;
elseif (bar())
    $foo = $bar
               ? $baz
               : $qux
                     ? $quux
                     : $quuux;
else
    $foo = $bar
               ? $baz
                     ? $qux
                     : $quux
               : $quuux;

PHP,
                <<<'PHP'
<?php
if (foo())
$foo = $bar
? $baz
? $qux
: $quux
: $quuux;
elseif (bar())
$foo = $bar
? $baz
: $qux
? $quux
: $quuux;
else
$foo = $bar
? $baz
? $qux
: $quux
: $quuux;
PHP,
            ],
            [
                <<<'PHP'
<?php
$a = $b->c(fn() =>
        $d &&
        $e,
    $f &&
        $g)
            ?: $start;

PHP,
                <<<'PHP'
<?php
$a = $b->c(fn() =>
$d &&
$e,
$f &&
$g)
?: $start;
PHP,
            ],
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
$alpha = $bravo
             ?: $charlie
             ?? $delta
             ?: $echo
             ?? $foxtrot;
$alpha =
    $bravo
        ?: $charlie
        ?? $delta
        ?: $echo
        ?? $foxtrot;
$alpha = $bravo
             ?? $charlie
             ?? $delta
             ?: $echo
             ?: $foxtrot;
$alpha =
    $bravo
        ?? $charlie
        ?? $delta
        ?: $echo
        ?: $foxtrot;
$alpha = $bravo
             ?: $charlie
             ?: $delta
             ?? $echo
             ?? $foxtrot;
$alpha =
    $bravo
        ?: $charlie
        ?: $delta
        ?? $echo
        ?? $foxtrot;
$alpha = $bravo ?: $charlie
             ?: $delta ?? $echo
             ?? $foxtrot;
$alpha = $bravo ?? $charlie
             ?? $delta ?: $echo
             ?: $foxtrot;
$alpha = $bravo ?? $charlie
             ?: $delta ?? $echo
             ?: $foxtrot;
$alpha = $bravo ?: $charlie
             ?? $delta ?: $echo
             ?? $foxtrot;

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
$alpha = $bravo
?: $charlie
?? $delta
?: $echo
?? $foxtrot;
$alpha =
$bravo
?: $charlie
?? $delta
?: $echo
?? $foxtrot;
$alpha = $bravo
?? $charlie
?? $delta
?: $echo
?: $foxtrot;
$alpha =
$bravo
?? $charlie
?? $delta
?: $echo
?: $foxtrot;
$alpha = $bravo
?: $charlie
?: $delta
?? $echo
?? $foxtrot;
$alpha =
$bravo
?: $charlie
?: $delta
?? $echo
?? $foxtrot;
$alpha = $bravo ?: $charlie
?: $delta ?? $echo
?? $foxtrot;
$alpha = $bravo ?? $charlie
?? $delta ?: $echo
?: $foxtrot;
$alpha = $bravo ?? $charlie
?: $delta ?? $echo
?: $foxtrot;
$alpha = $bravo ?: $charlie
?? $delta ?: $echo
?? $foxtrot;
PHP,
            ],
            [
                <<<'PHP'
<?php
$a = $b
         ?: $c or
             $d
                 ?: $e;
$a = $b
         ? $c
         : $d or
             $e
                 ? $f
                 : $g;
$a = $b
         ?: $c
         ?: $d or
             $e
                 ?: $f
                 ?: $g;
$a = $b
         ?? $c
         ?? $d or
             $e
                 ?? $f
                 ?? $g;
$a = $b
         ?? $c
         ?: $d
         ?? $e
         ?: $f or
             $g
                 ?? $h
                 ?: $i
                 ?? $j
                 ?: $k;
$a = $b
         ?: $c
         ?? $d
         ?: $e
         ?? $f or
             $g
                 ?: $h
                 ?? $i
                 ?: $j
                 ?? $k;

PHP,
                <<<'PHP'
<?php
$a = $b
?: $c or
$d
?: $e;
$a = $b
? $c
: $d or
$e
? $f
: $g;
$a = $b
?: $c
?: $d or
$e
?: $f
?: $g;
$a = $b
?? $c
?? $d or
$e
?? $f
?? $g;
$a = $b
?? $c
?: $d
?? $e
?: $f or
$g
?? $h
?: $i
?? $j
?: $k;
$a = $b
?: $c
?? $d
?: $e
?? $f or
$g
?: $h
?? $i
?: $j
?? $k;
PHP,
            ],
        ];
    }
}
