<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignChains;

final class HangingIndentationTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider ternaryOperatorsProvider
     */
    public function testTernaryOperators(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [AlignChains::class]);
    }

    /**
     * @return array<array{string,string}>
     */
    public static function ternaryOperatorsProvider(): array
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
        ];
    }

    /**
     * @dataProvider processTokenProvider
     */
    public function testProcessToken(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [AlignChains::class]);
    }

    /**
     * @return array<array{string,string}>
     */
    public static function processTokenProvider(): array
    {
        return [
            [
                <<<'PHP'
<?php
$a = $b->c(fn() =>
    $d &&
        $e)
    ?: $start;

PHP,
                <<<'PHP'
<?php
$a = $b->c(fn() =>
$d &&
$e)
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
        ];
    }
}
