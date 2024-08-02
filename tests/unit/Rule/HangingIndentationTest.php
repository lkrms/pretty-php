<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignChains;
use Lkrms\PrettyPHP\Tests\TestCase;

final class HangingIndentationTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [AlignChains::class]);
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
    $result = true
        ? 'true'
            ? 't'
            : false
        : 'f';
while (false);

PHP,
                <<<'PHP'
<?php
do
$result = true
? 'true'
? 't'
: false
: 'f';
while (false);
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
    a();
// comment
elseif (false)
    b();
// comment
else
    c();

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
a();
// comment
while (true);

if (true) {}
// comment
elseif (false) {}
// comment
else {}

if (true)
a();
// comment
elseif (false)
b();
// comment
else
c();
PHP,
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
            ],
            [
                <<<'PHP'
<?php
if (!$foo &&
    ($bar->qux !== Foo::BAR /* ||
        !$bar->quux() */)) {
    $foo = $bar;
}

PHP,
                <<<'PHP'
<?php
if (!$foo &&
        ($bar->qux !== Foo::BAR /* ||
            !$bar->quux() */)) {
    $foo = $bar;
}
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
            ],
        ];
    }
}
