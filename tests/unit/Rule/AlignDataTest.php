<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Rule\AlignLists;
use Lkrms\PrettyPHP\Rule\StrictLists;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class AlignDataTest extends TestCase
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
                          ->enable([AlignData::class]);
        $formatter = $formatterB->build();

        $input1 = <<<'PHP'
<?php
$a = $b = 0;
$bar = $baz = 1;
$quux = 2;

if ($foo) {
    $a = $b = 0;
    $bar = $baz = 1;
    $quux = 2;
}

if ($foo):
    $a = $b = 0;
    $bar = $baz = 1;
    $quux = 2;
endif;

array('a' => 0,
    'foo' => 1, 'bar' => 2,
    'quux' => 3, 'b' => 4);
array('a' => 0,
    'foo' => 1, 'bar' => 2,
    'quux' => 3, 'b' => 4,);

['a' => 0,
    'foo' => 1, 'bar' => 2,
    'quux' => 3, 'b' => 4];
['a' => 0,
    'foo' => 1, 'bar' => 2,
    'quux' => 3, 'b' => 4,];

function ($a = 0,
    $foo = 1, $bar = 2,
    $qux = 3, $b = 4) {};
fn($a = 0,
    $foo = 1, $bar = 2,
    $quux = 3, $b = 4) => null;
PHP;

        return [
            [
                <<<'PHP'
<?php
$a    = $b = 0;
$bar  = $baz = 1;
$quux = 2;

if ($foo) {
    $a    = $b = 0;
    $bar  = $baz = 1;
    $quux = 2;
}

if ($foo):
    $a    = $b = 0;
    $bar  = $baz = 1;
    $quux = 2;
endif;

array('a' => 0,
    'foo' => 1, 'bar' => 2,
    'quux' => 3, 'b' => 4);
array(
    'a'    => 0,
    'foo'  => 1,
    'bar'  => 2,
    'quux' => 3,
    'b'    => 4,
);

['a' => 0,
    'foo' => 1, 'bar' => 2,
    'quux' => 3, 'b' => 4];
[
    'a'    => 0,
    'foo'  => 1,
    'bar'  => 2,
    'quux' => 3,
    'b'    => 4,
];

function ($a = 0,
    $foo = 1, $bar = 2,
    $qux = 3, $b = 4) {};
fn($a = 0,
    $foo = 1, $bar = 2,
    $quux = 3, $b = 4) => null;

PHP,
                $input1,
                $formatter,
            ],
            [
                <<<'PHP'
<?php
$a    = $b = 0;
$bar  = $baz = 1;
$quux = 2;

if ($foo) {
    $a    = $b = 0;
    $bar  = $baz = 1;
    $quux = 2;
}

if ($foo):
    $a    = $b = 0;
    $bar  = $baz = 1;
    $quux = 2;
endif;

array('a'    => 0,
      'foo'  => 1, 'bar' => 2,
      'quux' => 3, 'b' => 4);
array(
    'a'    => 0,
    'foo'  => 1,
    'bar'  => 2,
    'quux' => 3,
    'b'    => 4,
);

['a'    => 0,
 'foo'  => 1, 'bar' => 2,
 'quux' => 3, 'b' => 4];
[
    'a'    => 0,
    'foo'  => 1,
    'bar'  => 2,
    'quux' => 3,
    'b'    => 4,
];

function ($a   = 0,
          $foo = 1, $bar = 2,
          $qux = 3, $b = 4) {};
fn($a    = 0,
   $foo  = 1, $bar = 2,
   $quux = 3, $b = 4) => null;

PHP,
                $input1,
                $formatterB
                    ->enable([AlignData::class, AlignLists::class])
                    ->build(),
            ],
            [
                <<<'PHP'
<?php
$a    = $b = 0;
$bar  = $baz = 1;
$quux = 2;

if ($foo) {
    $a    = $b = 0;
    $bar  = $baz = 1;
    $quux = 2;
}

if ($foo):
    $a    = $b = 0;
    $bar  = $baz = 1;
    $quux = 2;
endif;

array(
    'a'    => 0,
    'foo'  => 1,
    'bar'  => 2,
    'quux' => 3,
    'b'    => 4
);
array(
    'a'    => 0,
    'foo'  => 1,
    'bar'  => 2,
    'quux' => 3,
    'b'    => 4,
);

[
    'a'    => 0,
    'foo'  => 1,
    'bar'  => 2,
    'quux' => 3,
    'b'    => 4
];
[
    'a'    => 0,
    'foo'  => 1,
    'bar'  => 2,
    'quux' => 3,
    'b'    => 4,
];

function (
    $a   = 0,
    $foo = 1,
    $bar = 2,
    $qux = 3,
    $b   = 4
) {};
fn(
    $a    = 0,
    $foo  = 1,
    $bar  = 2,
    $quux = 3,
    $b    = 4
) => null;

PHP,
                $input1,
                $formatterB
                    ->enable([AlignData::class, StrictLists::class])
                    ->build(),
            ],
        ];
    }
}
