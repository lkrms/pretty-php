<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Rule\AlignChains;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;
use Lkrms\PrettyPHP\TokenIndex;

final class HangingIndentationTest extends TestCase
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
        $formatterB = Formatter::build()
                          ->flags(FormatterFlag::DETECT_PROBLEMS);
        $formatter = $formatterB->build();
        $withAlignChains = $formatterB
                               ->enable([AlignChains::class])
                               ->build();
        $withOperatorsLast = $formatterB
                                 ->tokenIndex(new TokenIndex(false, true))
                                 ->build();

        return [
            [
                <<<'PHP'
<?php
if (foo() ||
        bar()) {
    // baz
}

PHP,
                <<<'PHP'
<?php
if (foo() ||
bar()) {
// baz
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
function foo()
{
    static $a = 0,
        $b = 1,
        $c =
            2,
        $d = 3,
        $e = 4,
        $f = 5,
        $g = 6;
}

PHP,
                <<<'PHP'
<?php
function foo() {
static
$a = 0,
$b = 1,
$c =
2,
$d = 3,
$e = 4,
$f = 5,
$g = 6;
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
$abc = $def
    ->ghi()
    ->klm()
        ?: $abc;

PHP,
                <<<'PHP'
<?php
$abc = $def->ghi()
->klm()
?: $abc;
PHP,
                $formatter,
            ],
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
                $withAlignChains,
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
                $formatter,
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
                $formatter,
            ],
            [
                <<<'PHP'
<?php
do
    $foo = $bar ||
        $baz;
while (false);

PHP,
                <<<'PHP'
<?php
do
$foo = $bar ||
$baz;
while (false);
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
do {
    foo();
} while (
    $foo1 === $foo2 &&
    $bar1 ===
        $bar2 &&
    !($baz1 xor $baz2)
);

PHP,
                <<<'PHP'
<?php
do {
foo();
} while (
$foo1 === $foo2 &&
$bar1 ===
$bar2 &&
!($baz1 xor $baz2)
);
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
while (false):
    $foo = $bar ||
        $baz;
endwhile;

PHP,
                <<<'PHP'
<?php
while (false):
$foo = $bar ||
$baz;
endwhile;
PHP,
                $formatter,
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
                $formatter,
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
                $formatter,
            ],
            [
                <<<'PHP'
<?php
if ($a &&
        ($b ||
            $c) &&
        $d) {
    $e;
}

PHP,
                <<<'PHP'
<?php
if ($a &&
($b ||
$c) &&
$d) {
$e;
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
if (
    $a &&
    ($b = $c)->d() &&
    $e->f() &&
    ($g = h(
        $i
    ))->j !== $k &&
    (($l = $m)->n !== $o ||
        !(($p = $q) &&
            $r->s()) ||
        $t !== $u) &&
    !($v = $w)->x &&
    $y < $z
) {
    return $a->b;
}

PHP,
                <<<'PHP'
<?php
if (
$a &&
($b = $c)->d() &&
$e->f() &&
($g = h(
$i
))->j !== $k &&
(($l = $m)->n !== $o ||
!(($p = $q) &&
$r->s()) ||
$t !== $u) &&
!($v = $w)->x &&
$y < $z
) {
return $a->b;
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
function a($b, bool $c = false): bool
{
    return is_array($b) &&
        ($b
            ? count(array_filter($b, fn($i) => is_string($i))) === count($b) ||
                count(array_filter($b, fn($i) => is_int($i))) === count($b)
            : $c);
}

PHP,
                <<<'PHP'
<?php
function a($b, bool $c = false): bool
{
return is_array($b) &&
($b
? count(array_filter($b, fn($i) => is_string($i))) === count($b) ||
count(array_filter($b, fn($i) => is_int($i))) === count($b)
: $c);
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
try {
}
// comment
catch (Throwable $ex) {
}

do {
}
// comment
while (true);

do
    // comment
    a();
// comment
while (true);

if (true) {
}
// comment
elseif (false) {
}
// comment
else {
}

if (true)
    // comment
    a();
// comment
elseif (false)
    // comment
    b();
// comment
else
    // comment
    c();
// comment

PHP,
                <<<'PHP'
<?php
try {}
// comment
catch (Throwable $ex) {}

do {}
// comment
while (true);

do
// comment
a();
// comment
while (true);

if (true) {}
// comment
elseif (false) {}
// comment
else {}

if (true)
// comment
a();
// comment
elseif (false)
// comment
b();
// comment
else
// comment
c();
// comment
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
$foo = $bar->baz()
           ->quux([[$a,
               $b], $bar->thud()
                        ->grunt()])
           ->quuux();

PHP,
                <<<'PHP'
<?php
$foo = $bar->baz()
    ->quux([[$a,
        $b], $bar->thud()
        ->grunt()])
    ->quuux();
PHP,
                $withAlignChains,
            ],
            [
                <<<'PHP'
<?php
$a = array('b' => array('c' => 'd',
        'e' => 'f',
        'g' => 'h'),
    'i' => array(1,
        2,
        3,
        4,
        5,
        6),
    'j' => array('k',
        7 => 'l',
        'm'));

PHP,
                <<<'PHP'
<?php
$a = array('b' => array('c' => 'd',
'e' => 'f',
'g' => 'h'),
'i' => array(1,
2,
3,
4,
5,
6),
'j' => array('k',
7 => 'l',
'm'));
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
if (!$foo &&
    $bar->qux !== Foo::BAR /* &&
    !$bar->quux() */) {
    $foo = $bar;
}
if (!$foo &&
    ($bar->qux !== Foo::BAR /* ||
        !$bar->quux() */)) {
    $foo = $bar;
}

PHP,
                <<<'PHP'
<?php
if (!$foo &&
    $bar->qux !== Foo::BAR /* &&
    !$bar->quux() */) {
    $foo = $bar;
}
if (!$foo &&
    ($bar->qux !== Foo::BAR /* ||
        !$bar->quux() */)) {
    $foo = $bar;
}
PHP,
                $formatter,
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
                $formatter,
            ],
            [
                <<<'PHP'
<?php
$alpha = $bravo ??
    $charlie
    ?: $delta ??
    $echo
    ?: $foxtrot;
$alpha =
    $bravo ??
    $charlie
        ?: $delta ??
        $echo
        ?: $foxtrot;
$alpha = $bravo
    ?: $charlie ??
        $delta
    ?: $echo ??
        $foxtrot;
$alpha =
    $bravo
        ?: $charlie ??
            $delta
        ?: $echo ??
            $foxtrot;
$alpha = $bravo ??
    $charlie ??
    $delta
    ?: $echo
    ?: $foxtrot;
$alpha =
    $bravo ??
    $charlie ??
    $delta
        ?: $echo
        ?: $foxtrot;
$alpha = $bravo
    ?: $charlie
    ?: $delta ??
        $echo ??
        $foxtrot;
$alpha =
    $bravo
        ?: $charlie
        ?: $delta ??
            $echo ??
            $foxtrot;
$alpha = $bravo ?: $charlie
    ?: $delta ?? $echo ??
        $foxtrot;
$alpha = $bravo ?? $charlie ??
    $delta ?: $echo
    ?: $foxtrot;
$alpha = $bravo ?? $charlie
    ?: $delta ?? $echo
    ?: $foxtrot;
$alpha = $bravo ?: $charlie ??
    $delta ?: $echo ??
    $foxtrot;

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
                $withOperatorsLast,
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
                $formatter,
            ],
            [
                <<<'PHP'
<?php
$iterator = new RecursiveDirectoryIterator($dir,
    FilesystemIterator::KEY_AS_PATHNAME
        | FilesystemIterator::CURRENT_AS_FILEINFO
        | FilesystemIterator::SKIP_DOTS);

PHP,
                <<<'PHP'
<?php
$iterator = new RecursiveDirectoryIterator($dir,
FilesystemIterator::KEY_AS_PATHNAME
| FilesystemIterator::CURRENT_AS_FILEINFO
| FilesystemIterator::SKIP_DOTS);
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
fn($a, $b) =>
    $a === $b
        ? 0
        : $a <=>
            $b;

PHP,
                <<<'PHP'
<?php
fn($a, $b) =>
$a === $b
? 0
: $a <=>
$b;
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
$foo = $bar
    ? fn() =>
        (string) $baz
    : fn() =>
        qux();

PHP,
                <<<'PHP'
<?php
$foo = $bar
? fn() =>
(string) $baz
: fn() =>
qux();
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
!foo() ||
    $bar =
        <<<EOF
        Heredoc
        EOF;

PHP,
                <<<'PHP'
<?php
!foo() ||
$bar =
<<<EOF
Heredoc
EOF;
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
if (
    ($cm === 'RCDATA' ||
        $cm === 'CDATA') &&
    $es === true &&
    charfunc($tch, 3) === '-->'
) {
    $es = false;
}

PHP,
                <<<'PHP'
<?php
if (
($cm === 'RCDATA' ||
$cm === 'CDATA') && $es === true &&
charfunc($tch, 3) === '-->'
) {
$es = false;
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php declare(strict_types=1,ticks=1);

use My\{Bar,
    Baz,
    Qux};
use My\Bar,
    My\Baz,
    My\Qux;

class Foo
{
    use Bar,
        Baz,
        Qux;
    use Quux {
        Quux::foo insteadof Bar,
            Baz,
            Qux;
    }

    public const FOO = 1,
        BAR = 2,
        BAZ = 4;

    public const QUX =
            self::FOO
            | self::BAR
            | self::BAZ,
        QUUX = self::QUX
            | self::_FOO
            | self::_BAR;

    public int $Foo = self::FOO,
        $Bar = self::BAR,
        $Baz = self::BAZ;

    public int $Qux = self::QUX,
        $Quux =
            self::FOO
            | self::BAR
            | self::BAZ,
        $Quuux = self::QUX
            | self::_FOO
            | self::_BAR;
}

PHP,
                <<<'PHP'
<?php
declare(strict_types=1,
ticks=1);
use My\{Bar,
Baz,
Qux};
use My\Bar,
My\Baz,
My\Qux;
class Foo {
use Bar,
Baz,
Qux;
use Quux {
Quux::foo insteadof Bar,
Baz,
Qux;
}
public const FOO = 1,
BAR = 2,
BAZ = 4;
public const QUX =
self::FOO
| self::BAR
| self::BAZ, QUUX = self::QUX
| self::_FOO
| self::_BAR;
public int $Foo = self::FOO,
$Bar = self::BAR,
$Baz = self::BAZ;
public int $Qux = self::QUX,
$Quux =
self::FOO
| self::BAR
| self::BAZ,
$Quuux = self::QUX
| self::_FOO
| self::_BAR;
}
PHP,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
[$a
        + $b
        - $c, $d
        + $e
        - $f,
    $g
        + $h
        - $i];
[$a
        + $b
            * $c
            / $d
        - $e
            * $f
                ** $g
        + $h, $i
        + $j
            * $k
            / $l
        - $m
            * $n
                ** $o
        + $p,
    $q
        + $r
            * $s
            / $t
        - $u
            * $v
                ** $w
        + $x]
    ? $A
        + $B
            * $C
            / $D
        - $E
            * $F
                ** $G
        + $H
    : $I
        + $J
            * $K
            / $L
        - $M
            * $N
                ** $O
        + $P;
$a = $b
    ** $c
    * $d
    + $e
    - $f
        / $g
            ** $h;
$a =
    $b
    + $c
        * $d
            ** $e
    - $f
        / $g =
            $h
            + $i
                * $j
                    ** $k
            - $l
                / $m;
$a = $b
    + $c
        * $d
            ** $e
    - $f
        / $g = $h
            + $i
                * $j
                    ** $k
            - $l
                / $m;
$a =
    $b
    + $c
        * $d
            ** $e
    - $f
        / $g
            ? $h
                + $i
                    * $j
                        ** $k
                - $l
                    / $m
            : $n
                + $o
                    * $p
                        ** $q
                - $r
                    / $s;
$a =
    $b
    + $c
        * $d
            ** $e
    - $f
        / $g
            ? $h
                + $i
                    * $j
                        ** $k
                - $l
                    / $m =
                        $n
                        + $o
                            * $p
                                ** $q
                        - $r
                            / $s
            : $t
                + $u
                    * $v
                        ** $w
                - $x
                    / $y;

PHP,
                <<<'PHP'
<?php
[$a
+ $b
- $c, $d
+ $e
- $f,
$g
+ $h
- $i];
[$a
+ $b
* $c
/ $d
- $e
* $f
** $g
+ $h, $i
+ $j
* $k
/ $l
- $m
* $n
** $o
+ $p,
$q
+ $r
* $s
/ $t
- $u
* $v
** $w
+ $x]
? $A
+ $B
* $C
/ $D
- $E
* $F
** $G
+ $H
: $I
+ $J
* $K
/ $L
- $M
* $N
** $O
+ $P;
$a = $b
** $c
* $d
+ $e
- $f
/ $g
** $h;
$a =
$b
+ $c
* $d
** $e
- $f
/ $g =
$h
+ $i
* $j
** $k
- $l
/ $m;
$a = $b
+ $c
* $d
** $e
- $f
/ $g = $h
+ $i
* $j
** $k
- $l
/ $m;
$a =
$b
+ $c
* $d
** $e
- $f
/ $g
? $h
+ $i
* $j
** $k
- $l
/ $m
: $n
+ $o
* $p
** $q
- $r
/ $s;
$a =
$b
+ $c
* $d
** $e
- $f
/ $g
? $h
+ $i
* $j
** $k
- $l
/ $m =
$n
+ $o
* $p
** $q
- $r
/ $s
: $t
+ $u
* $v
** $w
- $x
/ $y;
PHP,
                $formatter,
            ],
        ];
    }
}
