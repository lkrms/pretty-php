<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Filter;

use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;
use Lkrms\PrettyPHP\TokenTypeIndex;

final class MoveCommentsTest extends TestCase
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

        $idx = new TokenTypeIndex();
        $mixed = $idx->withMixedOperators();
        $first = $idx->withLeadingOperators();
        $last = $idx->withTrailingOperators();

        $input1 = <<<'PHP'
<?php

$foo = [
    $bar // Comment
    , $baz // Comment
    , $qux // Comment
    ,
];

$foo = [
    $bar /** DocBlock */
    , $baz /** DocBlock */
    , $qux /** DocBlock */
    ,
];

function foo(
    $bar // Comment
    , $baz // Comment
    , $qux // Comment
): void {}

function bar(
    /** DocBlock */
    $foo /** DocBlock */
    , $baz /** DocBlock */
    , $qux /** DocBlock */
): void {}
PHP;

        $input2 = <<<'PHP'
<?php
$foo =  // Comment
    !  // Comment
    $qux  // Comment
        ? ($bar  // Comment
            * $baz  // Comment
            / 100)  // Comment
        . '%'  // Comment
        : $quux;  // Comment

$foo =  /* Comment */
    !  /* Comment */
    $qux  /* Comment */
        ? ($bar  /* Comment */
            * $baz  /* Comment */
            / 100)  /* Comment */
        . '%'  /* Comment */
        : $quux;  /* Comment */

$foo =  /** DocBlock */
    !  /** DocBlock */
    $qux  /** DocBlock */
        ? ($bar  /** DocBlock */
            * $baz  /** DocBlock */
            / 100)  /** DocBlock */
        . '%'  /** DocBlock */
        : $quux;  /** DocBlock */

$foo  // Comment
    =  // Comment
        !  // Comment
        $qux ?  // Comment
        ($bar *  // Comment
            $baz /  // Comment
            100) .  // Comment
        '%' :  // Comment
        $quux;  // Comment

$foo  /* Comment */
    =  /* Comment */
        !  /* Comment */
        $qux ?  /* Comment */
        ($bar *  /* Comment */
            $baz /  /* Comment */
            100) .  /* Comment */
        '%' :  /* Comment */
        $quux;  /* Comment */

$foo  /** DocBlock */
    =  /** DocBlock */
        !  /** DocBlock */
        $qux ?  /** DocBlock */
        ($bar *  /** DocBlock */
            $baz /  /** DocBlock */
            100) .  /** DocBlock */
        '%' :  /** DocBlock */
        $quux;  /** DocBlock */
PHP;

        $input3 = <<<'PHP'
<?php
fn() /* comment */ => null;
fn() => /* comment */ null;
fn()  /* comment */
    => null;
fn() =>  /* comment */
    null;
fn()  // comment
    => null;
fn() =>  // comment
    null;
[
    'foo' /* comment */ => 0,
    'bar' => /* comment */ 0,
    'baz'  // comment
        => 0,
    'qux' =>  // comment
        0,
];
PHP;

        return [
            'Comments before commas' => [
                <<<'PHP'
<?php

$foo = [
    $bar,  // Comment
    $baz,  // Comment
    $qux,  // Comment
];

$foo = [
    $bar,
    /** DocBlock */
    $baz,
    /** DocBlock */
    $qux,

    /**
     * DocBlock
     */
];

function foo(
    $bar,  // Comment
    $baz,  // Comment
    $qux  // Comment
): void {}

function bar(
    /** DocBlock */
    $foo,
    /** DocBlock */
    $baz,
    /** DocBlock */
    $qux

    /**
     * DocBlock
     */
): void {}

PHP,
                $input1,
                $formatter,
            ],
            'Comments before and after operators + mixed' => [
                <<<'PHP'
<?php
$foo =  // Comment
    // Comment
    !$qux  // Comment
        ? ($bar  // Comment
            * $baz  // Comment
            / 100)  // Comment
        . '%'  // Comment
        : $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    !$qux  /* Comment */
        ? ($bar  /* Comment */
            * $baz  /* Comment */
            / 100)  /* Comment */
        . '%'  /* Comment */
        : $quux;  /* Comment */

$foo =
    /** DocBlock */
    /** DocBlock */
    !$qux
        /** DocBlock */
        ? ($bar
            /** DocBlock */
            * $baz
            /** DocBlock */
            / 100)
        /** DocBlock */
        . '%'
        /** DocBlock */
        : $quux;
/** DocBlock */
$foo =  // Comment
    // Comment
    // Comment
    !$qux  // Comment
        ? ($bar  // Comment
            * $baz  // Comment
            / 100)  // Comment
        . '%'  // Comment
        : $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    /* Comment */
    !$qux  /* Comment */
        ? ($bar  /* Comment */
            * $baz  /* Comment */
            / 100)  /* Comment */
        . '%'  /* Comment */
        : $quux;  /* Comment */

$foo =
    /** DocBlock */
    /** DocBlock */
    /** DocBlock */
    !$qux
        /** DocBlock */
        ? ($bar
            /** DocBlock */
            * $baz
            /** DocBlock */
            / 100)
        /** DocBlock */
        . '%'
        /** DocBlock */
        : $quux;

/**
 * DocBlock
 */

PHP,
                $input2,
                $formatterB->tokenTypeIndex($mixed),
            ],
            'Comments before and after operators + first' => [
                <<<'PHP'
<?php
$foo =  // Comment
    // Comment
    !$qux  // Comment
        ? ($bar  // Comment
            * $baz  // Comment
            / 100)  // Comment
        . '%'  // Comment
        : $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    !$qux  /* Comment */
        ? ($bar  /* Comment */
            * $baz  /* Comment */
            / 100)  /* Comment */
        . '%'  /* Comment */
        : $quux;  /* Comment */

$foo =
    /** DocBlock */
    /** DocBlock */
    !$qux
        /** DocBlock */
        ? ($bar
            /** DocBlock */
            * $baz
            /** DocBlock */
            / 100)
        /** DocBlock */
        . '%'
        /** DocBlock */
        : $quux;
/** DocBlock */
$foo =  // Comment
    // Comment
    // Comment
    !$qux  // Comment
        ? ($bar  // Comment
            * $baz  // Comment
            / 100)  // Comment
        . '%'  // Comment
        : $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    /* Comment */
    !$qux  /* Comment */
        ? ($bar  /* Comment */
            * $baz  /* Comment */
            / 100)  /* Comment */
        . '%'  /* Comment */
        : $quux;  /* Comment */

$foo =
    /** DocBlock */
    /** DocBlock */
    /** DocBlock */
    !$qux
        /** DocBlock */
        ? ($bar
            /** DocBlock */
            * $baz
            /** DocBlock */
            / 100)
        /** DocBlock */
        . '%'
        /** DocBlock */
        : $quux;

/**
 * DocBlock
 */

PHP,
                $input2,
                $formatterB->tokenTypeIndex($first),
            ],
            'Comments before and after operators + last' => [
                <<<'PHP'
<?php
$foo =  // Comment
    // Comment
    !$qux  // Comment
        ? ($bar *  // Comment
            $baz /  // Comment
            100) .  // Comment
        '%'  // Comment
        : $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    !$qux  /* Comment */
        ? ($bar *  /* Comment */
            $baz /  /* Comment */
            100) .  /* Comment */
        '%'  /* Comment */
        : $quux;  /* Comment */

$foo =
    /** DocBlock */
    /** DocBlock */
    !$qux
        /** DocBlock */
        ? ($bar *
            /** DocBlock */
            $baz /
            /** DocBlock */
            100) .
        /** DocBlock */
        '%'
        /** DocBlock */
        : $quux;
/** DocBlock */
$foo =  // Comment
    // Comment
    // Comment
    !$qux  // Comment
        ? ($bar *  // Comment
            $baz /  // Comment
            100) .  // Comment
        '%'  // Comment
        : $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    /* Comment */
    !$qux  /* Comment */
        ? ($bar *  /* Comment */
            $baz /  /* Comment */
            100) .  /* Comment */
        '%'  /* Comment */
        : $quux;  /* Comment */

$foo =
    /** DocBlock */
    /** DocBlock */
    /** DocBlock */
    !$qux
        /** DocBlock */
        ? ($bar *
            /** DocBlock */
            $baz /
            /** DocBlock */
            100) .
        /** DocBlock */
        '%'
        /** DocBlock */
        : $quux;

/**
 * DocBlock
 */

PHP,
                $input2,
                $formatterB->tokenTypeIndex($last),
            ],
            'Comments after colons' => [
                <<<'PHP'
<?php
switch ($foo):  // Comment
    case 1:  // Comment
        label:  // Comment
        label:  // Comment
        break;
    default:  // Comment
        label:  // Comment
        break;
endswitch;
label:  // Comment
label:  // Comment
fn(): /* Comment */ ?int => null
?>
<?php
label:  // Comment
label:  // Comment

PHP,
                <<<'PHP'
<?php
switch ($foo):  // Comment
case 1:  // Comment
label:  // Comment
label:  // Comment
break;
default:  // Comment
label:  // Comment
break;
endswitch;
label:  // Comment
label:  // Comment
fn(): /* Comment */ ?int => null
?>
<?php
label:  // Comment
label:  // Comment
PHP,
                $formatter,
            ],
            'Comments before and after double arrows + mixed' => [
                <<<'PHP'
<?php
fn() => /* comment */ null;
fn() => /* comment */ null;
fn() =>  /* comment */
    null;
fn() =>  /* comment */
    null;
fn() =>  // comment
    null;
fn() =>  // comment
    null;
[
    'foo' => /* comment */ 0,
    'bar' => /* comment */ 0,
    'baz' =>  // comment
        0,
    'qux' =>  // comment
        0,
];

PHP,
                $input3,
                $formatterB->tokenTypeIndex($mixed),
            ],
            'Comments before and after double arrows + first' => [
                <<<'PHP'
<?php
fn() => /* comment */ null;
fn() => /* comment */ null;
fn() =>  /* comment */
    null;
fn() =>  /* comment */
    null;
fn() =>  // comment
    null;
fn() =>  // comment
    null;
[
    'foo' => /* comment */ 0,
    'bar' => /* comment */ 0,
    'baz' =>  // comment
        0,
    'qux' =>  // comment
        0,
];

PHP,
                $input3,
                $formatterB->tokenTypeIndex($first),
            ],
            'Comments before and after double arrows + last' => [
                <<<'PHP'
<?php
fn() => /* comment */ null;
fn() => /* comment */ null;
fn() =>  /* comment */
    null;
fn() =>  /* comment */
    null;
fn() =>  // comment
    null;
fn() =>  // comment
    null;
[
    'foo' => /* comment */ 0,
    'bar' => /* comment */ 0,
    'baz' =>  // comment
        0,
    'qux' =>  // comment
        0,
];

PHP,
                $input3,
                $formatterB->tokenTypeIndex($last),
            ],
            'Comments before and after double arrows + PSR-12' => [
                <<<'PHP'
<?php
fn() /* comment */ => null;
fn() /* comment */ => null;
fn()  /* comment */
    => null;
fn()  /* comment */
    => null;
fn()  // comment
    => null;
fn()  // comment
    => null;
[
    'foo' => /* comment */ 0,
    'bar' => /* comment */ 0,
    'baz' =>  // comment
        0,
    'qux' =>  // comment
        0,
];

PHP,
                $input3,
                $formatterB->psr12(),
            ],
        ];
    }
}
